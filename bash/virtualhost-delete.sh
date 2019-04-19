#!/bin/bash
# Set Language
TEXTDOMAIN=virtualhost
domain=$1
vHostFilePath=$2

# Check whether domain already exists
if ! [[ -e "$vHostFilePath" ]]; then
	echo -e $"No need to delete virtualhost. Domain <strong>$domain</strong> does not exist.\nPlease try another one"
	exit;
fi

# Delete domain in /etc/hosts
newhost=${domain//./\\.}
sed -i "/$newhost/d" /etc/hosts

# Disable website
a2dissite "$domain"

# Restart Apache
/etc/init.d/apache2 reload

# Delete virtual host rules files
rm "$vHostFilePath"

# Show the finished message
echo -e $"Successfully removed Virtual Host for $domain"
exit 0;