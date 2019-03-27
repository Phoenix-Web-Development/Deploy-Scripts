#!/bin/bash
### Set Language
TEXTDOMAIN=virtualhost

### Set default parameters
domain=$1
vHostFilePath=$2
hostEntry=$3

if [[ -z "$vHostFilePath" ]]; then
	echo -e $"No domain inputted."
	exit;
fi

### check if domain already exists
if [[ -e "$vHostFilePath" ]]; then
	echo -e $"This domain already exists."
	exit;
fi


### create virtual host rules file
if ! echo "$hostEntry" > $vHostFilePath
then
	echo -e $"There is an ERROR creating $domain file"
	exit;
fi


### Add domain in /etc/hosts
if ! echo "127.0.0.1	$domain" >> /etc/hosts
then
	echo $"ERROR: Not able to write in /etc/hosts"
	exit;
fi


### enable website
a2ensite $domain


### restart Apache
/etc/init.d/apache2 reload


### show the finished message
echo -e $"Successfully created virtual host for $domain."
exit;