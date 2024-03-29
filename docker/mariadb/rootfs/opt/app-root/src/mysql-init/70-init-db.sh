init_arbitrary_database() {
    local thisdir
    local init_data_file
    thisdir=$(dirname ${BASH_SOURCE[0]})

    mysql $mysql_flags << EOF
CREATE DATABASE IF NOT EXISTS ${DB_HKCCP_DATABASE};
CREATE USER IF NOT EXISTS '${DB_HKCCP_USERNAME}'@'%' IDENTIFIED BY '${DB_HKCCP_PASSWORD}';
CREATE USER IF NOT EXISTS '${DB_HKCCP_USERNAME}'@'127.0.0.1' IDENTIFIED BY '${DB_HKCCP_PASSWORD}';
GRANT ALL PRIVILEGES ON ${DB_HKCCP_DATABASE}.* TO '${DB_HKCCP_USERNAME}'@'%' IDENTIFIED BY '${DB_HKCCP_PASSWORD}';
GRANT ALL PRIVILEGES ON ${DB_HKCCP_DATABASE}.* TO '${DB_HKCCP_USERNAME}'@'127.0.0.1' IDENTIFIED BY '${DB_HKCCP_PASSWORD}';
FLUSH PRIVILEGES;
EOF

    mysql $mysql_flags << EOF
CREATE DATABASE IF NOT EXISTS ${DB_KOLAB_DATABASE};
CREATE USER IF NOT EXISTS ${DB_KOLAB_USERNAME}@'%' IDENTIFIED BY '${DB_KOLAB_PASSWORD}';
CREATE USER IF NOT EXISTS ${DB_KOLAB_USERNAME}@'127.0.0.1' IDENTIFIED BY '${DB_KOLAB_PASSWORD}';
GRANT ALL PRIVILEGES ON ${DB_KOLAB_DATABASE}.* TO ${DB_KOLAB_USERNAME}@'%' IDENTIFIED BY '${DB_KOLAB_PASSWORD}';
GRANT ALL PRIVILEGES ON ${DB_KOLAB_DATABASE}.* TO ${DB_KOLAB_USERNAME}@'127.0.0.1' IDENTIFIED BY '${DB_KOLAB_PASSWORD}';
FLUSH PRIVILEGES;
EOF

    mysql $mysql_flags << EOF
CREATE DATABASE IF NOT EXISTS $DB_RC_DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS $DB_RC_USERNAME@'%' IDENTIFIED BY '$DB_RC_PASSWORD';
CREATE USER IF NOT EXISTS $DB_RC_USERNAME@'127.0.0.1' IDENTIFIED BY '$DB_RC_PASSWORD';
ALTER USER $DB_RC_USERNAME@'%' IDENTIFIED BY '$DB_RC_PASSWORD';
ALTER USER $DB_RC_USERNAME@'127.0.0.1' IDENTIFIED BY '$DB_RC_PASSWORD';
GRANT ALL PRIVILEGES ON $DB_RC_DATABASE.* TO $DB_RC_USERNAME@'%';
GRANT ALL PRIVILEGES ON $DB_RC_DATABASE.* TO $DB_RC_USERNAME@'127.0.0.1';
FLUSH PRIVILEGES;
EOF

    # Powerdns setup according to https://github.com/PowerDNS/pdns/blob/master/modules/gmysqlbackend/schema.mysql.sql
    # Required for the first boot, afterwards the laravel migration will take over.
    # This is only required so pdns can start cleanly, indexes etc are handled by the laravel migration.
    mysql $mysql_flags ${DB_HKCCP_DATABASE} << EOF
CREATE TABLE powerdns_domains (
id                    INT AUTO_INCREMENT,
name                  VARCHAR(255) NOT NULL,
master                VARCHAR(128) DEFAULT NULL,
last_check            INT DEFAULT NULL,
type                  VARCHAR(8) NOT NULL,
notified_serial       INT UNSIGNED DEFAULT NULL,
account               VARCHAR(40) CHARACTER SET 'utf8' DEFAULT NULL,
options               VARCHAR(64000) DEFAULT NULL,
catalog               VARCHAR(255) DEFAULT NULL,
PRIMARY KEY (id)
) Engine=InnoDB CHARACTER SET 'latin1';

CREATE TABLE powerdns_records (
id                    BIGINT AUTO_INCREMENT,
domain_id             INT DEFAULT NULL,
name                  VARCHAR(255) DEFAULT NULL,
type                  VARCHAR(10) DEFAULT NULL,
content               VARCHAR(64000) DEFAULT NULL,
ttl                   INT DEFAULT NULL,
prio                  INT DEFAULT NULL,
disabled              TINYINT(1) DEFAULT 0,
ordername             VARCHAR(255) BINARY DEFAULT NULL,
auth                  TINYINT(1) DEFAULT 1,
PRIMARY KEY (id)
) Engine=InnoDB CHARACTER SET 'latin1';

CREATE TABLE powerdns_masters (
ip                    VARCHAR(64) NOT NULL,
nameserver            VARCHAR(255) NOT NULL,
account               VARCHAR(40) CHARACTER SET 'utf8' NOT NULL,
PRIMARY KEY (ip, nameserver)
) Engine=InnoDB CHARACTER SET 'latin1';

CREATE TABLE powerdns_comments (
id                    INT AUTO_INCREMENT,
domain_id             INT NOT NULL,
name                  VARCHAR(255) NOT NULL,
type                  VARCHAR(10) NOT NULL,
modified_at           INT NOT NULL,
account               VARCHAR(40) CHARACTER SET 'utf8' DEFAULT NULL,
comment               TEXT CHARACTER SET 'utf8' NOT NULL,
PRIMARY KEY (id)
) Engine=InnoDB CHARACTER SET 'latin1';


CREATE TABLE powerdns_cryptokeys (
id                    INT AUTO_INCREMENT,
domain_id             INT NOT NULL,
flags                 INT NOT NULL,
active                BOOL,
published             BOOL DEFAULT 1,
content               TEXT,
PRIMARY KEY(id)
) Engine=InnoDB CHARACTER SET 'latin1';


CREATE TABLE powerdns_tsigkeys (
id                    INT AUTO_INCREMENT,
name                  VARCHAR(255),
algorithm             VARCHAR(50),
secret                VARCHAR(255),
PRIMARY KEY (id)
) Engine=InnoDB CHARACTER SET 'latin1';

EOF

    init_data_file=$(readlink -f ${thisdir}/../mysql-data/roundcube.mysql.initial.sql)
    log_info "Initializing the arbitrary database from file ${init_data_file}..."
    mysql $mysql_flags ${DB_RC_DATABASE} < ${init_data_file}
}

if ! [ -v MYSQL_RUNNING_AS_SLAVE ] && $MYSQL_DATADIR_FIRST_INIT ; then
    init_arbitrary_database
fi
