#!/bin/bash
SCRIPT=$1
echo "Running "$SCRIPT" script through bash execution wrapper."
shift

if [[ "$(whoami)" != 'root' ]]; then
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
projectdir-setup)
  BASHFILE="projectdir-setup.sh"
  ;;
is-dir)
  BASHFILE="is-dir.sh"
  ;;
file-exists)
  BASHFILE="file-exists.sh"
  ;;
*)
  echo "That's not a legitimate bash script"
  exit 1
  ;;
esac
BASHFILE="../bash/$BASHFILE"
"$BASHFILE" "$@"