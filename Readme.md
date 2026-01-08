# Cron tasks manager

[![PHP Quality Checks](https://github.com/nenes25/cronjobs/actions/workflows/php.yml/badge.svg)](https://github.com/nenes25/cronjobs/actions/workflows/php.yml)

## About

Manage all your automated web tasks from a single interface.

This is a fork of the [official PrestaShop cronjobs module](https://github.com/PrestaShop/cronjobs) maintained by Hervé Hennes (hhennes) to provide better compatibility with recent PrestaShop versions and ongoing improvements.

### Key improvements in this fork:

- ✅ Full compatibility with PrestaShop 9.x
- ✅ Removal of deprecated webservice/Basic mode (use local cron only)
- ✅ Updated to use current PrestaShop APIs (replaced `Tools::encrypt` with `Tools::hash`)
- ✅ CI/CD workflows for quality assurance
- ✅ Modern PHP support (7.4 to 8.3)

## Installation

1. Download the latest release from the [Releases page](https://github.com/nenes25/cronjobs/releases)
2. Upload the module through your PrestaShop back office (Modules > Module Manager)
3. Install and configure the module

## Configuration

After installation, you need to set up a cron job on your server to execute the tasks:

```bash
# Execute cron tasks every hour
0 * * * * curl -k "https://yourstore.com/admin-folder/index.php?controller=AdminCronJobs&token=YOUR_TOKEN"
```

The complete command with your specific URL and token is available in the module configuration page.

## Contributing

PrestaShop modules are open-source extensions to the PrestaShop e-commerce solution. Everyone is welcome and even encouraged to contribute with their own improvements.

### Requirements

Contributors **must** follow the following rules:

* **Make your Pull Request on the "dev" branch**, NOT the "master" branch.
* Do not update the module's version number.
* Follow [the coding standards][1].

### Process in details

Contributors wishing to edit a module's files should follow the following process:

1. Create your GitHub account, if you do not have one already.
2. Fork the cronjobs project to your GitHub account.
3. Clone your fork to your local machine in the ```/modules``` directory of your PrestaShop installation.
4. Create a branch in your local clone of the module for your changes.
5. Change the files in your branch. Be sure to follow [the coding standards][1]!
6. Push your changed branch to your fork in your GitHub account.
7. Create a pull request for your changes **on the _'dev'_ branch** of the module's project. Be sure to follow [the commit message norm][2] in your pull request. If you need help to make a pull request, read the [Github help page about creating pull requests][3].
8. Wait for one of the core developers either to include your change in the codebase, or to comment on possible improvements you should make to your code.

That's it: you have contributed to this open-source project! Congratulations!

## License

Academic Free License (AFL 3.0)

## Credits

- Original module: [PrestaShop SA](https://github.com/PrestaShop/cronjobs)
- Fork maintainer: [Hervé Hennes (hhennes)](https://github.com/nenes25)

[1]: http://doc.prestashop.com/display/PS16/Coding+Standards
[2]: http://doc.prestashop.com/display/PS16/How+to+write+a+commit+message
[3]: https://help.github.com/articles/using-pull-requests

