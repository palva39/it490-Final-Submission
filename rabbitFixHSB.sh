#!/bin/bash


# Change IP depending on what server will be getting pinged


mainIP=”100.89.10.38”
backupIP="100.65.198.89" 
currentIP="$mainIP"
iniFile=”/var/bash/testRabbitMQ.ini”
pingIntervalInSeconds=15            
pingFailInSeconds=5               
pingAttempts=3    
isMainOn=true          


pingServer() {
   if (ping -c "$pingAttempts" -W "$pingFailInSeconds" "$mainIP" > /dev/null 2>&1); then
       isMainOn=true
   else
       isMainOn=false 
   fi
}


changeIP() {
   echo "Restarting Apache"
   newIP=$1
   sed -e "s/'$currentIP'/'$newIP'" -i "$iniFile"
   #sed "s/"$currentIP"/"$newIP"/" "$iniFile"
   currentIP="$newIP"
   systemctl restart apache2
}


# Below is the main checks running


while true; do
   pingServer


   if [ "$isMainOn" = true ]; then
        if [ "$currentIP" = "$backupIP" ]; then
            changeIP "$mainIP"
        fi
   elif [ "$isMainOn" = false ]; then
        if [ "$currentIP" = "$mainIP" ]; then
            changeIP "$backupIP"
        fi
   fi
  
   # Interval between pings to check server status
   sleep "$pingIntervalInSeconds"


done


