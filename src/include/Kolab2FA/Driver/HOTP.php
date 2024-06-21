<?php

/**
 * Kolab 2-Factor-Authentication HOTP driver implementation
 *
 * @author Thomas Bruederli <bruederli@kolabsys.com>
 *
 * Copyright (C) 2015, Kolab Systems AG <contact@kolabsys.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Kolab2FA\Driver;

class HOTP extends Base
{
    public $method = 'hotp';

    protected $config = array(
        'digits'   => 6,
        'window'   => 4,
        'digest'   => 'sha1',
    );

    protected $backend;

    /**
     *
     */
    public function init($config)
    {
        parent::init($config);

        $this->user_settings += array(
            'secret' => array(
                'type'      => 'text',
                'private'   => true,
                'label'     => 'secret',
                'generator' => 'generate_secret',
            ),
            'counter' => array(
                'type'      => 'integer',
                'editable'  => false,
                'hidden'    => true,
                'generator' => 'random_counter',
            ),
        );

        // copy config options
        $this->backend = \OTPHP\HOTP::create(
            null,
            0,
            $this->config['digest'],
            $this->config['digits']
        );

        $this->backend->setIssuer($this->config['issuer']);
        $this->backend->setIssuerIncludedAsParameter(true);
    }

    /**
     *
     */
    public function verify($code, $timestamp = null)
    {
        // get my secret from the user storage
        $secret  = $this->get('secret');
        $counter = (int) $this->get('counter');

        if (!strlen($secret)) {
            // LOG: "no secret set for user $this->username"
            // rcube::console("VERIFY HOTP: no secret set for user $this->username");
            return false;
        }

        try {
            $this->backend->setLabel($this->username);
            $this->backend->setSecret($secret);
            $this->backend->setParameter('counter', $counter);

            $pass = $this->backend->verify($code, $counter, $this->config['window']);

            // store incremented counter value
            $this->set('counter', $this->backend->getCounter());
            $this->commit();
        }
        catch (\Exception $e) {
            // LOG: exception
            // rcube::console("VERIFY HOTP: $this->id, " . strval($e));
            $pass = false;
        }

        // rcube::console('VERIFY HOTP', $this->username, $secret, $counter, $code, $pass);
        return $pass;
    }

    /**
     *
     */
    public function get_provisioning_uri()
    {
        if (!$this->secret) {
            // generate new secret and store it
            $this->set('secret', $this->get('secret', true));
            $this->set('counter', $this->get('counter', true));
            $this->set('created', $this->get('created', true));
            $this->commit();
        }

        // TODO: deny call if already active?

        $this->backend->setLabel($this->username);
        $this->backend->setSecret($this->secret);
        $this->backend->setParameter('counter', (int) $this->get('counter'));

        return $this->backend->getProvisioningUri();
    }

    /**
     * Generate a random counter value
     */
    public function random_counter()
    {
        return mt_rand(1, 999);
    }
}
