#!/bin/bash
echo 'ran wrapper'
SCRIPT=$1
shift

if [ "$(whoami)" != 'root' ]; then
	echo $"You have no permission to run $SCRIPT script as $(whoami) user."
	exit 1;
fi

case $SCRIPT in
virtualhost-create)
  BASHFILE="virtualhost-create.sh"
  ;;
virtualhost-delete)
  BASHFILE="virtualhost-delete.sh"
  ;;
webdir-create)
  BASHFILE="webdir-create.sh"
  ;;
*)
  echo "That's not a legitimate bash script"
  exit 1
  ;;
esac
BASHFILE="../bash/$BASHFILE"
$BASHFILE "$@"