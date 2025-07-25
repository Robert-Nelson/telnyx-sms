# Telnyx SMS
Freepbx add-on for SMS whish uses Telnyx as the provider.

## Installation

1. Download the module tarball using the FreePBX

    1. Select menu Admin / Module Admin

    2. Click Upload modules

    3. Select Type "Download (From Web)"

    4. in the text box "Download Remote Module" enter "https://github.com/Robert-Nelson/telnyx-sms/releases/latest/telnyx-sms.tbz;

2. In a shell prompt on the server execute the command: 

    `sudo php /var/www/html/telnyx_sms/install_root.php`

3. Back on the FreePBX Administration web site click "Manage local modules"

4. Scroll down to the Connectivity Section and select "Telnyx SMS"

5. In the Action section click "Install"

6. Scroll down and click the "Process" button.

### That completes the installation ###

## Setup

Now complete the setup by clicking the "Telnyx SMS" menu item in the Connectivity Menu.
