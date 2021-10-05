#!/bin/bash

sed -i -r \
    -e '/allowplaintext/ a\
guam_allowplaintext: yes' \
    -e '/allowplaintext/ a\
nginx_allowplaintext: yes' \
    /etc/imapd.conf

sed -i \
    -e '/SERVICES/ a\
    nginx cmd="imapd" listen=127.0.0.1:12143 prefork=1' \
    -e '/SERVICES/ a\
    guam cmd="imapd" listen=127.0.0.1:13143 prefork=1' \
    -e '/SERVICES/ a\
    imap cmd="imapd" listen=127.0.0.1:11143 prefork=1' \
    -e 's/listen="127.0.0.1:9993"/listen=127.0.0.1:11993/g' \
    /etc/cyrus.conf

systemctl restart cyrus-imapd

# Remove the submission block, by matching from submission until the next empty line
sed -i -e '/submission          inet/,/^$/d' /etc/postfix/master.cf

# Insert a new submission block with a modified port
cat >> /etc/postfix/master.cf << EOF
127.0.0.1:10587     inet        n       -       n       -       -       smtpd
    -o cleanup_service_name=cleanup_submission
    -o syslog_name=postfix/submission
    #-o smtpd_tls_security_level=encrypt
    -o smtpd_sasl_auth_enable=yes
    -o smtpd_sasl_authenticated_header=yes
    -o smtpd_client_restrictions=permit_sasl_authenticated,reject
    -o smtpd_data_restrictions=\$submission_data_restrictions
    -o smtpd_recipient_restrictions=\$submission_recipient_restrictions
    -o smtpd_sender_restrictions=\$submission_sender_restrictions

127.0.0.1:10465     inet        n       -       n       -       -       smtpd
    -o cleanup_service_name=cleanup_submission
    -o rewrite_service_name=rewrite_submission
    -o syslog_name=postfix/smtps
    -o mydestination=
    -o local_recipient_maps=
    -o relay_domains=
    -o relay_recipient_maps=
    #-o smtpd_tls_wrappermode=yes
    -o smtpd_sasl_auth_enable=yes
    -o smtpd_sasl_authenticated_header=yes
    -o smtpd_client_restrictions=permit_sasl_authenticated,reject
    -o smtpd_sender_restrictions=\$submission_sender_restrictions
    -o smtpd_recipient_restrictions=\$submission_recipient_restrictions
    -o smtpd_data_restrictions=\$submission_data_restrictions
EOF

systemctl restart postfix

cat > /etc/guam/sys.config << EOF
%% Example configuration for Guam.
[
    {
        kolab_guam, [
            {
                imap_servers, [
                    {
                        imap, [
                            { host, "127.0.0.1" },
                            { port, 13143 },
                            { tls, no }
                        ]
                    },
                    {
                        imaps, [
                            { host, "127.0.0.1" },
                            { port, 11993 },
                            { tls, true }
                        ]
                    }
                ]
            },
            {
                listeners, [
                    {
                        imap, [
                            { port, 9143 },
                            { imap_server, imap },
                            {
                                rules, [
                                    { filter_groupware, [] }
                                ]
                            },
                            {
                                tls_config, [
                                    { certfile, "/etc/pki/cyrus-imapd/cyrus-imapd.pem" }
                                ]
                            }
                        ]
                    },
                    {
                        imaps, [
                            { port, 9993 },
                            { implicit_tls, true },
                            { imap_server, imaps },
                            {
                                rules, [
                                    { filter_groupware, [] }
                                ]
                            },
                            {
                                tls_config, [
                                    { certfile, "/etc/pki/cyrus-imapd/cyrus-imapd.pem" }
                                ]
                            }
                        ]
                    }
                ]
            }
        ]
    },

    {
        lager, [
            {
                handlers, [
                    { lager_console_backend, warning },
                    { lager_file_backend, [ { file, "log/error.log"}, { level, error } ] },
                    { lager_file_backend, [ { file, "log/console.log"}, { level, info } ] }
                ]
            }
        ]
    },

    %% SASL config
    {
        sasl, [
            { sasl_error_logger, { file, "log/sasl-error.log" } },
            { errlog_type, error },
            { error_logger_mf_dir, "log/sasl" },      % Log directory
            { error_logger_mf_maxbytes, 10485760 },   % 10 MB max file size
            { error_logger_mf_maxfiles, 5 }           % 5 files max
        ]
    }
].
EOF

systemctl restart guam
