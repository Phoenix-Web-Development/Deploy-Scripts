#!/bin/bash

passphrase=$1
key_name=$2
rootDir=$3
if [ "$rootDir" == "" ]; then
	rootDir="~/.ssh/"
fi
key_file=$rootDir$key_name
if [ !-f $key_file ]; then
   ssh-keygen -q -t rsa -N $passphrase -f $key_file
fi
chown james:james $key_file
chown james:james $key_file.pub

echo -e ${cat $key_file.pub}