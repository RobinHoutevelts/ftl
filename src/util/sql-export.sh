#!/bin/bash

# Set generic things
HOST=127.0.0.1
STDOUT=false

# colors
GREEN='\033[0;32m'
RED='\033[31m'
DEFAULT_COLOR='\033[0;0m'

# Get type-specific config
if [[ ${POSTGRES_DB} != '' ]]; then
  DATABASE=${POSTGRES_DB:-database}
  PORT=5432
  USER=postgres
else
  DATABASE=${MYSQL_DATABASE:-database}
  PORT=${MYSQL_PORT:-port}
  USER=${MYSQL_USER:-root}
  PASSWORD=${MYSQL_PASSWORD:-}
fi

# Set the default filename
FILE=${DATABASE}.`date +"%Y-%m-%d-%s"`.sql

# PARSE THE ARGZZ
# TODO: compress the mostly duplicate code below?
while (( "$#" )); do
  case "$1" in
    -h|--host|--host=*)
      if [ "${1##--host=}" != "$1" ]; then
        HOST="${1#*=}"
        shift
      else
        HOST="$2"
        shift 2
      fi
      ;;
    -db|--database|--database=*)
      if [ "${1##--database=}" != "$1" ]; then
        DATABASE="${1#*=}"
        shift
      else
        DATABASE="$2"
        shift 2
      fi
      ;;
    --stdout)
        STDOUT=true
        shift
      ;;
    --)
      shift
      break
      ;;
    -*|--*=)
      shift
      ;;
    *)
      FILE="$(pwd)/$1"
      shift
      ;;
  esac
done

dump_db() {
  if [[ ${POSTGRES_DB} != '' ]]; then
    pg_dump postgresql://$USER@localhost:$PORT/$DATABASE
    return
  fi

  mysqldump --column-statistics=0 --opt --user=${USER} --password=${PASSWORD} --host=${HOST} --port=${PORT} ${DATABASE};

}

# Do the dump to stdout
if [ "$STDOUT" == "true" ]; then
  dump_db
else

  # Clean up last dump before we dump again
  unalias rm 2> /dev/null
  rm ${FILE} 2> /dev/null
  dump_db > ${FILE}

  # Show the user the result
  if [ $? -ne 0 ]; then
    rm ${FILE}
    echo -e "${RED}Failed ${DEFAULT_COLOR}to create file: ${FILE}"
    exit 1
  else
    # Gzip the mysql database dump file
    gzip $FILE
    # Reset perms on linux
    if [ "$LANDO_HOST_OS" = "linux" ]; then
      chown $LANDO_HOST_UID:$LANDO_HOST_GID "${FILE}.gz"
    fi
    # Report
    echo -e "${GREEN}Success${DEFAULT_COLOR} ${FILE}.gz was created!"
  fi
fi
