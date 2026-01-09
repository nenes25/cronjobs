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

## License

Academic Free License (AFL 3.0)

## Credits

- Original module: [PrestaShop SA](https://github.com/PrestaShop/cronjobs)
- Fork maintainer: [Hervé Hennes (hhennes)](https://github.com/nenes25)
