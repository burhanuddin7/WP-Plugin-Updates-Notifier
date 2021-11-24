
# To include this file in settings option of WP dashboard add the following code in function.php

require_once 'includes/admin/plugin-updates-notifier.php';


# WP Plugin Update Notifier

Implementation

- Created a submenu page in the settings options of the WP dashboard where in there are options given to enable/disable notification and turn off plugin update reminder.

- The alert will be triggered everyday and it is checked on the basis of current date, the alert will be triggered on slack as well as on email.

- Once the plugin updates has been notified, then that particular plugins latest version is stored in DB and the next alert will be fired when the version has been changed.

- The new updates can be figured in the last column of the email where in 'Latest update available' message is printed for the plugins whose new version has been rolled out.

- After first update notification, new alerts will only be triggered if there are new versions available for the existing plugins.

- By checking the change log in each of the active plugin triggered in the alert we can get to know about its upgrades, new features as well as its compatibility with the current WP version and PHP version.

- We can plan an action item based on the details obtained from the change log as in which plugin needs to be updated to its current version for our benefit.

- The Plugin Update notifier page in the settings option has the option to disable the alerts as well as add new slack channel name for alerts.

- Below is the attached SS for how the alert message will be displayed on the mail:

<img width="1058" alt="Screenshot 2021-11-24 at 5 42 46 PM" src="https://user-images.githubusercontent.com/17512774/143236364-68bbbe8f-d3cf-4a8f-9591-1e4b8212cf25.png"><img width="1058" alt="Screenshot 2021-11-24 at 5 45 35 PM" src="https://user-images.githubusercontent.com/17512774/143236757-67725618-cfc4-43ec-b783-9c14db43a407.png">

