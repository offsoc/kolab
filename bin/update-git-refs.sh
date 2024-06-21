#!/bin/bash

updateVar() {
    NAME=$1
    #TODO pin option that translates to a commit hash via
    # git ls-remote --exit-code -h "https://git.kolab.org/source/kolab" refs/heads/master
    REF=$2
    if ! grep -q "$NAME=" src/.env; then
        echo "$1=$REF" >> src/.env
    else
        echo "s/$NAME=.*/$NAME=$REF/"
        sed -i "s|$NAME=.*|$NAME=$REF|" src/.env
    fi
}

updateVar KOLAB_GIT_REF "${KOLAB_GIT_REF:-master}"
updateVar KOLAB_GIT_REMOTE "${KOLAB_GIT_REMOTE:-https://git.kolab.org/source/kolab}"
updateVar GIT_REF_ROUNDCUBEMAIL "${GIT_REF_ROUNDCUBEMAIL:-dev/kolab-1.5}"
updateVar GIT_REMOTE_ROUNDCUBEMAIL "${GIT_REMOTE_ROUNDCUBEMAIL:-https://git.kolab.org/source/roundcubemail.git}"
updateVar GIT_REF_ROUNDCUBEMAIL_PLUGINS "${GIT_REF_ROUNDCUBEMAIL_PLUGINS:-master}"
updateVar GIT_REMOTE_ROUNDCUBEMAIL_PLUGINS "${GIT_REMOTE_ROUNDCUBEMAIL_PLUGINS:-https://git.kolab.org/diffusion/RPK/roundcubemail-plugins-kolab.git}"
updateVar GIT_REF_CHWALA "${GIT_REF_CHWALA:-master}"
updateVar GIT_REMOTE_CHWALA "${GIT_REMOTE_CHWALA:-https://git.kolab.org/diffusion/C/chwala.git}"
updateVar GIT_REF_SYNCROTON "${GIT_REF_SYNCROTON:-master}"
updateVar GIT_REMOTE_SYNCROTON "${GIT_REMOTE_SYNCROTON:-https://git.kolab.org/diffusion/S/syncroton.git}"
updateVar GIT_REF_AUTOCONF "${GIT_REF_SYNCROTON:-master}"
updateVar GIT_REMOTE_AUTOCONF "${GIT_REMOTE_AUTOCONF:-https://git.kolab.org/diffusion/AC/autoconf.git}"
updateVar GIT_REF_IRONY "${GIT_REF_IRONY:-master}"
updateVar GIT_REMOTE_IRONY "${GIT_REMOTE_IRONY:-https://git.kolab.org/source/iRony.git}"
updateVar GIT_REF_FREEBUSY "${GIT_REF_FREEBUSY:-master}"
updateVar GIT_REMOTE_FREEBUSY "${GIT_REMOTE_FREEBUSY:-https://git.kolab.org/diffusion/F/freebusy.git}"
updateVar IMAP_GIT_REF "${IMAP_GIT_REF:-dev/mollekopf}"
updateVar IMAP_GIT_REMOTE "${IMAP_GIT_REMOTE:-https://git.kolab.org/source/cyrus-imapd}"
