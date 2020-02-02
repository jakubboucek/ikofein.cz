#!/bin/bash

FOLDERS=(
    "app"
    "libs"
    "vendor"
    "www"
)
REMOTE_DIR="/var/www/ikofein.cz/www"
LOCAL_DIR=""
SERVER_NAME="ralph"

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
SSH="ssh ${SERVER_NAME}"

for folder in "${FOLDERS[@]}"; do
    echo "Uploading folder /$folder to SSH…"
    rsync -rcP --delete --exclude-from="${DIR}/.rsync-exclude" "${DIR}${LOCAL_DIR}/$folder" "${SERVER_NAME}:$REMOTE_DIR/"
done

echo "Replace file permissions…"
${SSH} sudo fixwww ${REMOTE_DIR}

echo "Remove temporary files…"
${SSH} find ${REMOTE_DIR}/temp -mindepth 2 -type f -delete

echo -n "Remove nette email-sent marker… "
${SSH} /bin/bash << EOF
	if [ -f ${REMOTE_DIR}/log/email-sent ]
	then
		rm ${REMOTE_DIR}/log/email-sent
		echo "removed"
	else
		echo "no exists"
	fi
EOF
