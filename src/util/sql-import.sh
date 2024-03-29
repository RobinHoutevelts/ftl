#!/bin/bash

# Set generic config
FILE=""
WIPE=true
HOST=127.0.0.1

GREEN='\033[0;32m'
RED='\033[0;31m'
DEFAULT_COLOR='\033[0;0m'

# Get type-specific config
if [[ ${POSTGRES_DB} != '' ]]; then
  DATABASE=${POSTGRES_DB:-database}
  PORT=5432
  USER=postgres
else
  DATABASE=${MYSQL_DATABASE:-database}
  PORT=${MYSQL_PORT:-port}
  USER=root
fi

# PARSE THE ARGZZ
while (("$#")); do
  case "$1" in
  -h | --host | --host=*)
    if [ "${1##--host=}" != "$1" ]; then
      HOST="${1#*=}"
      shift
    else
      HOST="$2"
      shift 2
    fi
    ;;
  -db | --database | --database=*)
    if [ "${1##--database=}" != "$1" ]; then
      DATABASE="${1#*=}"
      shift
    else
      DATABASE="$2"
      shift 2
    fi
    ;;
  -port | --port | --port=*)
    if [ "${1##--port=}" != "$1" ]; then
      PORT="${1#*=}"
      shift
    else
      PORT="$2"
      shift 2
    fi
    ;;
  --no-wipe)
    WIPE=false
    shift
    ;;
  --)
    shift
    break
    ;;
  -* | --*=)
    shift
    ;;
  *)
    FILE="$1"
    shift
    ;;
  esac
done

# Set positional arguments in their proper place
eval set -- "$FILE"
PV=""
CMD=""

# Use file or stdin
if [ ! -z "$FILE" ]; then

  # Validate we have a file
  if [ ! -f "$FILE" ]; then
    echo "File $FILE not found!"
    exit 1
  fi

  CMD="$FILE"

else

  # Build DB specific connection string
  if [[ ${POSTGRES_DB} != '' ]]; then
    CMD="psql postgresql://$USER@$HOST:$PORT/$DATABASE"
  else
    CMD="mysql -h $HOST -P $PORT -u $USER"
  fi

  # Read stdin into DB
  $CMD #>/dev/null
  exit 0

fi

# Inform the user of things
echo "Preparing to import $FILE into $DATABASE on $HOST:$PORT as $USER..."

# Wipe the database if set
if [ "$WIPE" == "true" ]; then

  echo "Destroying all current tables in $DATABASE... "
  echo "NOTE: See the --no-wipe flag to avoid this step!"

  # DO db specific wiping
  if [[ ${POSTGRES_DB} != '' ]]; then

    # Drop and recreate database
    printf "\t\t${GREEN}Dropping database ...\n\n${DEFAULT_COLOR}"
    psql postgresql://$USER@$HOST:$PORT/postgres -c "drop database $DATABASE"

    printf "\t\t${GREEN}Creating database ...\n\n${DEFAULT_COLOR}"
    psql postgresql://$USER@$HOST:$PORT/postgres -c "create database $DATABASE"

  else

    # Build the SQL prefix
    SQLSTART="mysql -h $HOST -P $PORT -u $USER $DATABASE"

    # Gather and destroy tables
    TABLES=$($SQLSTART -e 'SHOW TABLES' | awk '{ print $1}' | grep -v '^Tables')

    # PURGE IT ALL! BURN IT TO THE GROUND!!!
    for t in $TABLES; do
      if [[ $t == view_* ]]; then
        continue
      fi
      echo "Dropping $t table from $DATABASE database..."
      $SQLSTART -e "SET FOREIGN_KEY_CHECKS=0;DROP TABLE $t"
    done
    $SQLSTART -e "SET FOREIGN_KEY_CHECKS=1"
  fi
fi

# Check to see if we have any unzipping options or GUI needs
if command -v gunzip >/dev/null 2>&1 && gunzip -t $FILE >/dev/null 2>&1; then
  echo "Gzipped file detected!"
  if command -v pv >/dev/null 2>&1; then
    CMD="pv $CMD"
  else
    CMD="cat $CMD"
  fi
  CMD="$CMD | gunzip"
elif command -v unzip >/dev/null 2>&1 && unzip -t $FILE >/dev/null 2>&1; then
  echo "Zipped file detected!"
  CMD="unzip -p $CMD"
  if command -v pv >/dev/null 2>&1; then
    CMD="$CMD | pv"
  fi
else
  if command -v pv >/dev/null 2>&1; then
    CMD="pv $CMD"
  else
    CMD="cat $CMD"
  fi
fi

# Build DB specific import command
if [[ ${POSTGRES_DB} != '' ]]; then
  CMD="$CMD | psql postgresql://$USER@$HOST:$PORT/$DATABASE"
else
  CMD="$CMD | mysql -h $HOST -P $PORT -u $USER $DATABASE"
fi

# Import
echo "Importing $FILE..."
if command eval "$CMD"; then
  STATUS=$?
else
  STATUS=1
fi

# Finish up!
if [ $STATUS -eq 0 ]; then
  echo ""
  printf "${GREEN}Import complete!${DEFAULT_COLOR}"
  echo ""
else
  echo ""
  printf "${RED}Import failed.${DEFAULT_COLOR}"
  exit $STATUS
  echo ""
fi
