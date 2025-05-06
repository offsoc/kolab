<?php

namespace Tests\Feature\Controller;

use Carbon\Carbon;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\RegisteredClaims;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use Lcobucci\JWT\Validation\Validator;
use Tests\TestCase;

class VPNTest extends TestCase
{
    protected function setUp(): void
    {
        Carbon::setTestNow(Carbon::create(2022, 2, 2, 13, 0, 0));
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Test the webhook
     */
    public function testToken(): void
    {
        // openssl genpkey -algorithm RSA -out private.pem -pkeyopt rsa_keygen_bits:2048
        $privateKey = <<<'EOF'
            -----BEGIN PRIVATE KEY-----
            MIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQCmfUb5J+na6LV1
            6PTmOX6alvyEbWEUA1HykKFsKRq3q8nraZU4LlfhM1qoeA23re4WEmT2SoCXq8sd
            ewRXP+swYl6vAPZFtqCR52vApEXn+b221g3gEa5KnZeQ3APTej6wcs+3etMcDFAh
            Sabrjvl3eoHXmO2RQoeSjSfp6N4LPvbbOjkLR5EYdDNxVuwU7HRkwkXve1rWh94z
            RbSl7o4XkP/qzOGEDfrm7SivcSWqwFndHkxNeum1FnBfDZ9ChOwEunuIZiPX2TDb
            K3WZ+TJn29xNERAJJgFZLspB9RK+FT/lCX4IZgdl8ZyU8lC68QXaZuAcQhg7TU7l
            xBw7ZJXBAgMBAAECggEAAqEL/R9tJmISNYJgKv+MQ9mFCNFSXQwgCpN6nRg5aLOb
            h25o0b+M7zc4pAbrTokpBJZgm5wPE6C99HakGahJKOqGF2o4OV8aLNupnFvrvQSj
            reQLnuF17g3hiMMqA02qTkPYiwud6FLlyV9zSu58etoc0UsEBg0gfMcNCDj/bVAt
            b+JvZjfFXG9TQRNx7F3H/yRO5kHw+oqeDBAvHN+Mkq9KATLQ8GBZznwpN7fHL+Is
            PZCDvcAg/4OtN1cNgMOKkdBDSMzjtn7xHUX9jl03VUPvXfa9KDdFCMdVndtnACRK
            lFNMdD+ugQ7Ch1oHI16HUWo663fjF+9m3neYjoAiwQKBgQC+UykotVMHX65OsYx7
            tXankRmj9WnoiUFrfClrdVynbkcbZvCt9NdNluv0eNRbEaNXD07nOIppACSDaQ0i
            DdIALbFU6I3kzAF+z25Rh6SaIl5gaUGxSLSzmpswJsv0hiu/5Sb8t6fOX+VWBV7I
            Fw7MqASoggV5rEAvRVtwiw4TsQKBgQDf8IwZDwXlTFc7/VsAjLfb+edpVcavOB9d
            DAXAwUH+2FJb9AWPOLrTnKdsRV9yw9FueMciuROGi9btOYxEcLGvNcvsyaWy0eFQ
            vN2w0NsxpBodRylxMQLGDc5wO9lbrVuftzC0rDaQaD9gPQaKFoQI7ww81Jmx6u1Y
            OP/Nsq53EQKBgD8vIoHmOItBI3/yh53mL18P18BLz/4n2vURAjsvejQHc0nQkeRe
            XT/f87N0jaMyJtTXOy2d4q1bI8QQkxCUH/x5Lt7uWXT0mSZ9PLWKX4XgFQ7SwsFV
            TtA1aoHAz4L9K/cH3zqUyfvEcEFvhPjOVtZwjSNYDvNG0QQgdWvWbjTxAoGAE50R
            6C/0qDyjd1GdYtLwV4fvyL4GhNo5hQDEkDlc+mEf9YXN5tllI5uY3lbFIVwdP7u8
            VUI4f5RH4scjjesA5QOlNLwEk0DmpxejoxTn3dUtpFrTOmK8h3Q2HIZhZzIr0DVP
            QsPCk6tNwbQWmomWTuIBBGLqgza8SvnTDcUUmsECgYBXk4MAk1FennJuDvpiD1Gg
            HiIQykUOQeA5wk+R97X7D4kI8kj6XzCuRjG+nSJiYmpZBHRvA5XtRtMJWep11R6O
            8yu8ftj4xBQr6roUoHwJ/JBxe8JuKW3yh52CaZLP2KjizwzNI0hDMUzinZIReex+
            iFyt8WwtMwzW/520PlwUyQ==
            -----END PRIVATE KEY-----
            EOF;

        // openssl rsa -pubout -in private.pem -out public.pem
        $publicKey = <<<'EOF'
            -----BEGIN PUBLIC KEY-----
            MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEApn1G+Sfp2ui1dej05jl+
            mpb8hG1hFANR8pChbCkat6vJ62mVOC5X4TNaqHgNt63uFhJk9kqAl6vLHXsEVz/r
            MGJerwD2RbagkedrwKRF5/m9ttYN4BGuSp2XkNwD03o+sHLPt3rTHAxQIUmm6475
            d3qB15jtkUKHko0n6ejeCz722zo5C0eRGHQzcVbsFOx0ZMJF73ta1ofeM0W0pe6O
            F5D/6szhhA365u0or3ElqsBZ3R5MTXrptRZwXw2fQoTsBLp7iGYj19kw2yt1mfky
            Z9vcTREQCSYBWS7KQfUSvhU/5Ql+CGYHZfGclPJQuvEF2mbgHEIYO01O5cQcO2SV
            wQIDAQAB
            -----END PUBLIC KEY-----
            EOF;

        \config(['app.vpn.token_signing_key' => $privateKey]);

        $john = $this->getTestUser('john@kolab.org');

        $response = $this->get("api/v4/vpn/token");
        $response->assertStatus(401);

        $response = $this->actingAs($john)->get("api/v4/vpn/token");
        $response->assertStatus(200);

        $json = $response->json();
        $jwt = $json['token'];

        $parser = new Parser(new JoseEncoder());

        /** @var UnencryptedToken $token */
        $token = $parser->parse($jwt);

        $this->assertSame("default", $token->claims()->get('entitlement'));
        $issued_at = $token->claims()->get(RegisteredClaims::ISSUED_AT);
        $this->assertSame("2022-02-02T13:00:00+00:00", $issued_at->format(\DateTimeImmutable::RFC3339));
        $this->assertSame(0.0, Carbon::now()->diffInSeconds(new Carbon($issued_at)));

        $validator = new Validator();
        $key = InMemory::plainText($publicKey);
        $validator->assert($token, new Constraint\SignedWith(new Sha256(), $key));

        $invalidKey = <<<'EOF'
            -----BEGIN PUBLIC KEY-----
            MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEApn1G+Sfp2ui1dej05jl+
            mpb8hG1hFANR8pChbCkat6vJ62mVOC5X4TNaqHgNt63uFhJk9kqAl6vLHXsEVz/r
            MGJerwD2RbagkedrwKRF5/m9ttYN4BGuSp2XkNwD03o+sHLPt3rTHAxQIUmm6475
            d3qB15jtkUKHko0n6ejeCz722zo5C0eRGHQzcVbsFOx0ZMJF73ta1ofeM0W0pe6O
            F5D/6szhhA365u0or3ElqsBZ3R5MTXrptRZwXw2fQoTsBLp7iGYj19kw2yt1mfky
            Z9vcTREQCSYBWS7KQfUSvhU/5Ql+CGYHZfGclPJQuvEF2mbgHEIYO01O5cQcO2SV
            wQIDAQAC
            -----END PUBLIC KEY-----
            EOF;
        $this->expectException(RequiredConstraintsViolated::class);
        $key = InMemory::plainText($invalidKey);
        $validator->assert($token, new Constraint\SignedWith(new Sha256(), $key));
    }
}
