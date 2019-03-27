#!/bin/bash
### Set Language
TEXTDOMAIN=virtualhost

### Set default parameters
domain=$1
vHostFilePath=$2


### don't modify from here unless you know what you are doing ####

while [ "$domain" == "" ]
do
	echo -e $"Please provide domain. e.g.dev,staging"
	read domain
done


### check whether domain already exists
if ! [ -e $vHostFilePath ]; then
	echo -e $"This domain does not exist.\nPlease try another one"
	exit;
fi

### Delete domain in /etc/hosts
newhost=${domain//./\\.}
sed -i "/$newhost/d" /etc/hosts

### disable website
a2dissite $domain

### restart Apache
/etc/init.d/apache2 reload

### Delete virtual host rules files
rm $vHostFilePath

### show the finished message

echo -e $"Successfully removed Virtual Host for $domain"
exit 0;