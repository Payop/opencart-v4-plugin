Opencart v4 Payop Payment Gateway
=====================

## Brief Description

Add the ability to accept payments in Opencart v4 via Payop.com.

## Requirements

-  Opencart 4.0+


## Installation Guide for Payop in OpenCart 4 (via Admin Panel)

### 1. Download the Latest Version
- Go to the [latest release](https://github.com/Payop/opencart-v4-plugin/releases).  
- Select the latest version from the list of releases (the one at the top is the most recent).  
- Download the `payop.ocmod.zip` file.  

### 2. Install the Module via OpenCart Admin Panel
1. Log in to your **OpenCart admin panel**.  
2. Navigate to **Extensions → Installer**.  
3. Click the **Upload** button and select the downloaded `payop.ocmod.zip` file.  
4. Wait for the installation to complete.  

### 3. Enable and Configure the Module
1. Go to **Extensions → Extensions**.  
2. In the dropdown menu, select **Payments**.  
3. Find **Payop Payment Gateway** and click the **Install** button.  
4. After installation, click the **Edit** button.  
5. Enter your **Public Key** and **Secret Key** (see the section below for details on how to obtain them).  
6. In the module settings, the **IPN URL** field will automatically generate a link for processing payments. Copy this link.  
7. Go to **Payop.com → IPN → Add new IPN**, paste the copied link, and save it.  

### 4. Refresh Modifications
1. Navigate to **Extensions → Modifications**.  
2. Click the **Refresh** button at the top right to apply changes.  

### 5. Obtain Public Key and Secret Key in Payop
1. Log in to your account at [Payop.com](https://payop.com).  
2. Go to **Projects → Projects list**.  
3. Select your project and click **Details**.  
4. You will find **Public Key** and **Secret Key** in the project settings.  
5. Copy them and paste them into the module settings in OpenCart.  

## Support

* [Open an issue](https://github.com/Payop/opencart-v4-plugin/issues) if you are having issues with this plugin.
* [Payop Documentation](https://payop.com/en/documentation/common/)
* [Contact Payop support](https://payop.com/en/contact-us/)
  
**TIP**: When contacting support it will help us is you provide:

* Opencart Version
* Other plugins you have installed
  * Some plugins do not play nice
* Configuration settings for the plugin (Most merchants take screen grabs)
* Any log files that will help
  * Web server error logs
* Screen grabs of error message if applicable.

## Contribute

Would you like to help with this project?  Great!  You don't have to be a developer, either.
If you've found a bug or have an idea for an improvement, please open an
[issue](https://github.com/Payop/opencart-v4-plugin/issues) and tell us about it.

If you *are* a developer wanting contribute an enhancement, bugfix or other patch to this project,
please fork this repository and submit a pull request detailing your changes.  We review all PRs!

This open source project is released under the [MIT license](http://opensource.org/licenses/MIT)
which means if you would like to use this project's code in your own project you are free to do so.


## License

Please refer to the 
[LICENSE](https://github.com/Payop/opencart-v4-plugin/blob/master/LICENSE)
file that came with this project.
