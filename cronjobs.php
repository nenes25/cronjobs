<?php

/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

if (defined('_PS_ADMIN_DIR_') === false) {
    define('_PS_ADMIN_DIR_', _PS_ROOT_DIR_ . '/admin/');
}

require_once dirname(__FILE__) . '/classes/CronJobsForms.php';

/**
 * CronJobs module class for managing scheduled tasks
 */
class CronJobs extends Module
{
    /**
     * Constant to indicate a task should run at each interval
     */
    public const EACH = -1;

    /**
     * @var array Success messages
     */
    protected $_successes;

    /**
     * @var array Warning messages
     */
    protected $_warnings;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->name = 'cronjobs';
        $this->tab = 'administration';
        $this->version = '2.0.1';
        $this->module_key = '';
        $this->controllers = ['callback'];
        $this->author = 'PrestaShop / hhennes';
        $this->need_instance = true;
        $this->bootstrap = true;
        $this->display = 'view';

        parent::__construct();

        if ($this->id) {
            $this->init();
        }

        $this->displayName = $this->trans('Cron tasks manager', [], 'Modules.Cronjobs.Admin');
        $this->description = $this->trans('Focus on your business, don\'t lose time working on minor tasks: automate it!', [], 'Modules.Cronjobs.Admin');

        if (function_exists('curl_init') == false) {
            $this->warning = $this->trans('To be able to use this module, please activate cURL (PHP extension).', [], 'Modules.Cronjobs.Admin');
        }
    }

    /**
     * Module installation
     *
     * @return bool
     */
    public function install(): bool
    {
        Configuration::updateValue('CRONJOBS_ADMIN_DIR', Tools::hash($this->getAdminDir()));
        Configuration::updateValue('CRONJOBS_MODULE_VERSION', $this->version);

        $token = Tools::hash(Tools::getShopDomainSsl() . time());
        Configuration::updateGlobalValue('CRONJOBS_EXECUTION_TOKEN', $token);

        if (parent::install()) {
            return $this->installDb() && $this->installTab()
                && $this->registerHook('actionModuleRegisterHookAfter')
                && $this->registerHook('actionModuleUnRegisterHookAfter')
                && $this->registerHook('backOfficeHeader');
        }

        return false;
    }

    /**
     * Get admin dir
     *
     * @return string
     */
    protected function getAdminDir(): string
    {
        return basename(_PS_ADMIN_DIR_);
    }

    /**
     * Initialize module configuration and check for updates
     *
     * @return void
     */
    protected function init()
    {
        $new_admin_dir = (Tools::hash($this->getAdminDir()) != Configuration::get('CRONJOBS_ADMIN_DIR'));
        $new_module_version = version_compare($this->version, Configuration::get('CRONJOBS_MODULE_VERSION'), '!=');

        if ($new_admin_dir || $new_module_version) {
            Configuration::updateValue('CRONJOBS_MODULE_VERSION', $this->version);
            Configuration::updateValue('CRONJOBS_ADMIN_DIR', Tools::hash($this->getAdminDir()));
        }
    }

    /**
     * Module uninstallation
     *
     * @return bool
     */
    public function uninstall(): bool
    {
        return $this->uninstallDb()
            && $this->uninstallTab()
            && parent::uninstall();
    }

    /**
     * Create module database tables
     *
     * @return bool
     */
    public function installDb(): bool
    {
        return Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . bqSQL($this->name) . ' (
            `id_cronjob` INTEGER(10) NOT NULL AUTO_INCREMENT,
            `id_module` INTEGER(10) DEFAULT NULL,
            `description` TEXT DEFAULT NULL,
            `task` TEXT DEFAULT NULL,
            `hour` INTEGER DEFAULT \'-1\',
            `day` INTEGER DEFAULT \'-1\',
            `month` INTEGER DEFAULT \'-1\',
            `day_of_week` INTEGER DEFAULT \'-1\',
            `updated_at` DATETIME DEFAULT NULL,
            `one_shot` BOOLEAN NOT NULL DEFAULT 0,
            `active` BOOLEAN DEFAULT FALSE,
            `id_shop` INTEGER DEFAULT \'0\',
            `id_shop_group` INTEGER DEFAULT \'0\',
            PRIMARY KEY(`id_cronjob`),
            INDEX (`id_module`))
            ENGINE=' . _MYSQL_ENGINE_ . ' default CHARSET=utf8'
        );
    }

    /**
     * Drop module database tables
     *
     * @return bool True if successful, false otherwise
     */
    /**
     * Drop module database tables
     *
     * @return bool True if successful, false otherwise
     */
    public function uninstallDb(): bool
    {
        return Db::getInstance()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . bqSQL($this->name));
    }

    /**
     * Install module tab
     *
     * @return bool True if successful, false otherwise
     */
    public function installTab(): bool
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->name = [];
        $tab->class_name = 'AdminCronJobs';

        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'Cron Jobs';
        }

        $tab->id_parent = -1;
        $tab->module = $this->name;

        return $tab->add();
    }

    /**
     * Uninstall module tab
     *
     * @return bool True if successful, false otherwise
     */
    public function uninstallTab()
    {
        $id_tab = (int) Tab::getIdFromClassName('AdminCronJobs');

        if ($id_tab) {
            $tab = new Tab($id_tab);

            return $tab->delete();
        }

        return false;
    }

    /**
     * Hook called after a module registers a hook
     *
     * @param array $params Hook parameters
     *
     * @return void
     */
    public function hookActionModuleRegisterHookAfter(array $params): void
    {
        $hook_name = $params['hook_name'];

        if ($hook_name == 'actionCronJob') {
            $module = $params['object'];
            $this->registerModuleHook($module->id);
        }
    }

    /**
     * Hook called after a module unregisters a hook
     *
     * @param array $params Hook parameters
     *
     * @return void
     */
    public function hookActionModuleUnRegisterHookAfter(array $params): void
    {
        $hook_name = $params['hook_name'];

        if ($hook_name == 'actionCronJob') {
            $module = $params['object'];
            $this->unregisterModuleHook($module->id);
        }
    }

    /**
     * Hook for adding CSS/JS to back office header
     *
     * @return void
     */
    public function hookBackOfficeHeader(): void
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addCSS($this->_path . 'views/css/admin/configure.css');
        }
    }

    /**
     * Get module configuration page content
     *
     * @return string HTML content
     */
    public function getContent(): string
    {
        $output = null;
        CronJobsForms::init($this);
        $this->checkLocalEnvironment();

        if (Tools::isSubmit('submitNewCronJob')) {
            $submit_cron = $this->postProcessNewJob();
        } elseif (Tools::isSubmit('submitUpdateCronJob')) {
            $submit_cron = $this->postProcessUpdateJob();
        }

        $this->context->smarty->assign([
            'module_dir' => $this->_path,
            'module_local_dir' => $this->local_path,
        ]);

        $this->context->smarty->assign('form_errors', $this->_errors);
        $this->context->smarty->assign('form_infos', $this->_warnings);
        $this->context->smarty->assign('form_successes', $this->_successes);

        if ((Tools::isSubmit('submitNewCronJob') || Tools::isSubmit('newcronjobs') || Tools::isSubmit('updatecronjobs'))
            && ((isset($submit_cron) == false) || ($submit_cron === false))) {
            $back_url = $this->context->link->getAdminLink('AdminModules', false)
                . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name
                . '&token=' . Tools::getAdminTokenLite('AdminModules');
        }

        $output = $output . $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

        if (Tools::isSubmit('newcronjobs') || ((isset($submit_cron) == true) && ($submit_cron === false))) {
            $output = $output . $this->renderForm(CronJobsForms::getJobForm(), CronJobsForms::getNewJobFormValues(), 'submitNewCronJob', true, $back_url);
        } elseif (Tools::isSubmit('updatecronjobs') && Tools::isSubmit('id_cronjob')) {
            $form_structure = CronJobsForms::getJobForm('Update cron task', true);
            $form = $this->renderForm(
                $form_structure,
                CronJobsForms::getUpdateJobFormValues(),
                'submitUpdateCronJob',
                true,
                $back_url,
                true
            );

            $output = $output . $form;
        } elseif (Tools::isSubmit('deletecronjobs') && Tools::isSubmit('id_cronjob')) {
            $this->postProcessDeleteCronJob((int) Tools::getValue('id_cronjob'));
        } elseif (Tools::isSubmit('oneshotcronjobs')) {
            $this->postProcessUpdateJobOneShot();
        } elseif (Tools::isSubmit('statuscronjobs')) {
            $this->postProcessUpdateJobStatus();
        } else {
            $output = $output . $this->renderForm(CronJobsForms::getForm(), CronJobsForms::getFormValues(), 'submitCronJobs');
        }

        return $output . $this->renderTasksList();
    }

    /**
     * Send callback response and close connection before executing cron tasks
     *
     * @return void
     */
    public function sendCallback(): void
    {
        ignore_user_abort(true);
        set_time_limit(0);

        ob_start();
        echo $this->name . '_prestashop';
        header('Connection: close');
        header('Content-Length: ' . ob_get_length());
        ob_end_flush();
        ob_flush();
        flush();

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    /**
     * Check if a module's cron job is active
     *
     * @param int $id_module Module ID
     *
     * @return bool True if active, false otherwise
     */
    public static function isActive($id_module): bool
    {
        $module = Module::getInstanceByName('cronjobs');

        if (($module == false) || ($module->active == false)) {
            return false;
        }

        $query = 'SELECT `active` FROM ' . _DB_PREFIX_ . 'cronjobs WHERE `id_module` = \'' . (int) $id_module . '\'';

        return (bool) Db::getInstance()->getValue($query);
    }

    /**
     * Add a one-shot cron task
     *
     * @param string $task Valid URL task
     * @param string $description Task description
     * @param array $execution Execution schedule (hour, day, month, day_of_week)
     *
     * @return bool True if successful, false otherwise
     */
    public static function addOneShotTask($task, $description, $execution = [])
    {
        if (self::isTaskURLValid($task) == false) {
            return false;
        }

        $id_shop = (int) Context::getContext()->shop->id;
        $id_shop_group = (int) Context::getContext()->shop->id_shop_group;

        $query = 'SELECT `active` FROM ' . _DB_PREFIX_ . 'cronjobs
            WHERE `task` = \'' . urlencode($task) . '\' AND `updated_at` IS NULL
                AND `one_shot` IS TRUE
                AND `id_shop` = \'' . $id_shop . '\' AND `id_shop_group` = \'' . $id_shop_group . '\'';

        if ((bool) Db::getInstance()->getValue($query) == true) {
            return true;
        }

        if (count($execution) == 0) {
            $query = 'INSERT INTO ' . _DB_PREFIX_ . 'cronjobs
                (`description`, `task`, `hour`, `day`, `month`, `day_of_week`, `updated_at`, `one_shot`, `active`, `id_shop`, `id_shop_group`)
                VALUES (\'' . Db::getInstance()->escape($description) . '\', \'' .
                urlencode($task) . '\', \'0\', \'' . CronJobs::EACH . '\', \'' . CronJobs::EACH . '\', \'' . CronJobs::EACH . '\',
                    NULL, TRUE, TRUE, ' . $id_shop . ', ' . $id_shop_group . ')';

            return Db::getInstance()->execute($query);
        } else {
            $is_frequency_valid = true;
            $hour = (int) $execution['hour'];
            $day = (int) $execution['day'];
            $month = (int) $execution['month'];
            $day_of_week = (int) $execution['day_of_week'];

            $is_frequency_valid = (($hour >= -1) && ($hour < 24) && $is_frequency_valid);
            $is_frequency_valid = (($day >= -1) && ($day <= 31) && $is_frequency_valid);
            $is_frequency_valid = (($month >= -1) && ($month <= 31) && $is_frequency_valid);
            $is_frequency_valid = (($day_of_week >= -1) && ($day_of_week < 7) && $is_frequency_valid);

            if ($is_frequency_valid == true) {
                $query = 'INSERT INTO ' . _DB_PREFIX_ . 'cronjobs
                    (`description`, `task`, `hour`, `day`, `month`, `day_of_week`, `updated_at`, `one_shot`, `active`, `id_shop`, `id_shop_group`)
                    VALUES (\'' . Db::getInstance()->escape($description) . '\', \'' .
                    urlencode($task) . '\', \'' . $hour . '\', \'' . $day . '\', \'' . $month . '\', \'' . $day_of_week . '\',
                        NULL, TRUE, TRUE, ' . $id_shop . ', ' . $id_shop_group . ')';

                return Db::getInstance()->execute($query);
            }
        }

        return false;
    }

    /**
     * Check if running in a local environment and display warning
     *
     * @return void
     */
    protected function checkLocalEnvironment(): void
    {
        if ($this->isLocalEnvironment() == true) {
            $this->setWarningMessage('You are using the Cron jobs module on a local installation:
                you may not be able to reliably call remote cron tasks in your current environment.
                To use this module at its best, you should switch to an online installation.');
        }
    }

    /**
     * Check if the environment is a local installation
     *
     * @return bool True if local, false otherwise
     */
    protected function isLocalEnvironment(): bool
    {
        if (isset($_SERVER['REMOTE_ADDR']) === false) {
            return true;
        }

        $is_a_local_ip = in_array(Tools::getRemoteAddr(), ['127.0.0.1', '::1']);
        $is_a_local_shop_domain = preg_match(
            '/^172\.16\.|^192\.168\.|^10\.|^127\.|^localhost|\.local$/',
            Configuration::get('PS_SHOP_DOMAIN')
        );

        return $is_a_local_ip || $is_a_local_shop_domain;
    }

    /**
     * Render a configuration form
     *
     * @param array $form Form structure
     * @param array $form_values Form values
     * @param string $action Submit action name
     * @param bool $cancel Show cancel button
     * @param string|bool $back_url Back URL
     * @param bool $update Is update mode
     *
     * @return string HTML form content
     */
    protected function renderForm($form, $form_values, $action, $cancel = false, $back_url = false, $update = false): string
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = $action;

        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
        . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;

        if ($update == true) {
            $helper->currentIndex .= '&id_cronjob=' . (int) Tools::getValue('id_cronjob');
        }

        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => $form_values,
            'id_language' => $this->context->language->id,
            'languages' => $this->context->controller->getLanguages(),
            'back_url' => $back_url,
            'show_cancel_button' => $cancel,
        ];

        return $helper->generateForm($form);
    }

    /**
     * Render the list of custom cron tasks
     *
     * @return string HTML list content
     */
    protected function renderTasksList(): string
    {
        $helper = new HelperList();

        $helper->title = $this->trans('Cron tasks', [], 'Modules.Cronjobs.Admin');
        $helper->table = $this->name;
        $helper->no_link = true;
        $helper->shopLinkType = '';
        $helper->identifier = 'id_cronjob';
        $helper->actions = ['edit', 'delete'];

        $values = CronJobsForms::getTasksListValues();
        $helper->listTotal = count($values);
        $helper->tpl_vars = ['show_filters' => false];

        $helper->toolbar_btn['new'] = [
            'href' => $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name
            . '&newcronjobs=1&token=' . Tools::getAdminTokenLite('AdminModules'),
            'desc' => $this->trans('Add new task', [], 'Modules.Cronjobs.Admin'),
        ];

        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;

        return $helper->generateList($values, CronJobsForms::getTasksList());
    }

    /**
     * Process the creation of a new cron job
     *
     * @return bool True if successful, false otherwise
     */
    protected function postProcessNewJob(): bool
    {
        if ($this->isNewJobValid() == true) {
            $description = Db::getInstance()->escape(Tools::getValue('description'));
            $task = urlencode(Tools::getValue('task'));
            $hour = (int) Tools::getValue('hour');
            $day = (int) Tools::getValue('day');
            $month = (int) Tools::getValue('month');
            $day_of_week = (int) Tools::getValue('day_of_week');

            $result = Db::getInstance()->getRow('SELECT id_cronjob FROM ' . _DB_PREFIX_ . bqSQL($this->name) . '
                WHERE `task` = \'' . $task . '\' AND `hour` = \'' . $hour . '\' AND `day` = \'' . $day . '\'
                AND `month` = \'' . $month . '\' AND `day_of_week` = \'' . $day_of_week . '\'');

            if ($result == false) {
                $id_shop = (int) Context::getContext()->shop->id;
                $id_shop_group = (int) Context::getContext()->shop->id_shop_group;

                $query = 'INSERT INTO ' . _DB_PREFIX_ . bqSQL($this->name) . '
                    (`description`, `task`, `hour`, `day`, `month`, `day_of_week`, `updated_at`, `active`, `id_shop`, `id_shop_group`)
                    VALUES (\'' . $description . '\', \'' . $task . '\', \'' . $hour . '\', \'' . $day . '\', \'' . $month . '\', \'' . $day_of_week . '\', NULL, TRUE, ' . $id_shop . ', ' . $id_shop_group . ')';

                if (($result = Db::getInstance()->execute($query)) != false) {
                    return $this->setSuccessMessage('The task has been successfully added.');
                }

                return $this->setErrorMessage('An error happened: the task could not be added.');
            }

            return $this->setErrorMessage('This cron task already exists.');
        }

        return false;
    }

    /**
     * Process the update of an existing cron job
     *
     * @return bool True if successful, false otherwise
     */
    protected function postProcessUpdateJob()
    {
        if (Tools::isSubmit('id_cronjob') == false) {
            return false;
        }

        $description = Db::getInstance()->escape(Tools::getValue('description'));
        $task = urlencode(Tools::getValue('task'));
        $hour = (int) Tools::getValue('hour');
        $day = (int) Tools::getValue('day');
        $month = (int) Tools::getValue('month');
        $day_of_week = (int) Tools::getValue('day_of_week');
        $id_cronjob = (int) Tools::getValue('id_cronjob');

        $query = 'UPDATE ' . _DB_PREFIX_ . bqSQL($this->name) . '
            SET `description` = \'' . $description . '\',
                `task` = \'' . $task . '\',
                `hour` = \'' . $hour . '\',
                `day` = \'' . $day . '\',
                `month` = \'' . $month . '\',
                `day_of_week` = \'' . $day_of_week . '\'
            WHERE `id_cronjob` = \'' . (int) $id_cronjob . '\'';

        if (Db::getInstance()->execute($query) != false) {
            return $this->setSuccessMessage('The task has been updated.');
        }

        return $this->setErrorMessage('The task has not been updated');
    }

    /**
     * Add cron tasks for newly installed modules
     *
     * @return bool|void False if no crons found, void otherwise
     */
    public function addNewModulesTasks()
    {
        $crons = Hook::getHookModuleExecList('actionCronJob');
        $table_name = _DB_PREFIX_ . bqSQL($this->name);

        if ($crons == false) {
            return false;
        }

        $id_shop = (int) Context::getContext()->shop->id;
        $id_shop_group = (int) Context::getContext()->shop->id_shop_group;

        foreach ($crons as $cron) {
            $id_module = (int) $cron['id_module'];
            $module = Module::getInstanceById((int) $cron['id_module']);

            if ($module == false && isset($cron['id_cronjob'])) {
                Db::getInstance()->execute(sprintf("DELETE FROM '%s' WHERE `id_cronjob` = '%s'", $table_name, (int) $cron['id_cronjob']));
                break;
            }

            $select_query = sprintf(
                "SELECT `id_cronjob` FROM `%s`
                WHERE `id_module` = '%s' AND `id_shop` = '%s' AND `id_shop_group` = '%s'",
                $table_name,
                $id_module,
                $id_shop,
                $id_shop_group
            );

            $cronjob = (bool) Db::getInstance()->getValue($select_query);

            if ($cronjob == false) {
                $this->registerModuleHook($id_module);
            }
        }
    }

    /**
     * Toggle the one-shot status of a cron job
     *
     * @return bool False if no ID submitted, void otherwise (redirects)
     */
    protected function postProcessUpdateJobOneShot()
    {
        if (Tools::isSubmit('id_cronjob') == false) {
            return false;
        }

        $id_cronjob = (int) Tools::getValue('id_cronjob');

        Db::getInstance()->execute('UPDATE ' . _DB_PREFIX_ . bqSQL($this->name) . '
            SET `one_shot` = IF (`one_shot`, 0, 1) WHERE `id_cronjob` = \'' . (int) $id_cronjob . '\'');

        Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name
            . '&token=' . Tools::getAdminTokenLite('AdminModules'));
    }

    /**
     * Toggle the active status of a cron job
     *
     * @return bool False if no ID submitted, void otherwise (redirects)
     */
    protected function postProcessUpdateJobStatus()
    {
        if (Tools::isSubmit('id_cronjob') == false) {
            return false;
        }

        $id_cronjob = (int) Tools::getValue('id_cronjob');

        Db::getInstance()->execute('UPDATE ' . _DB_PREFIX_ . bqSQL($this->name) . '
            SET `active` = IF (`active`, 0, 1) WHERE `id_cronjob` = \'' . (int) $id_cronjob . '\'');

        Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name
            . '&token=' . Tools::getAdminTokenLite('AdminModules'));
    }

    /**
     * Validate new cron job data from form submission
     *
     * @return bool True if valid, false otherwise
     */
    protected function isNewJobValid()
    {
        if ((Tools::isSubmit('description') == true)
            && (Tools::isSubmit('task') == true)
            && (Tools::isSubmit('hour') == true)
            && (Tools::isSubmit('day') == true)
            && (Tools::isSubmit('month') == true)
            && (Tools::isSubmit('day_of_week') == true)) {
            if (self::isTaskURLValid(Tools::getValue('task')) == false) {
                return $this->setErrorMessage('The target link you entered is not valid. It should be an absolute URL, on the same domain as your shop.');
            }

            $hour = Tools::getValue('hour');
            $day = Tools::getValue('day');
            $month = Tools::getValue('month');
            $day_of_week = Tools::getValue('day_of_week');

            return $this->isFrequencyValid($hour, $day, $month, $day_of_week);
        }

        return false;
    }

    /**
     * Validate cron job frequency parameters
     *
     * @param int $hour Hour (0-23 or -1 for each)
     * @param int $day Day of month (1-31 or -1 for each)
     * @param int $month Month (1-12 or -1 for each)
     * @param int $day_of_week Day of week (0-6 or -1 for each)
     *
     * @return bool True if valid, false otherwise
     */
    protected function isFrequencyValid($hour, $day, $month, $day_of_week)
    {
        $success = true;

        if ((($hour >= -1) && ($hour < 24)) == false) {
            $success &= $this->setErrorMessage('The value you chose for the hour is not valid. It should be between 00:00 and 23:59.');
        }
        if ((($day >= -1) && ($day <= 31)) == false) {
            $success &= $this->setErrorMessage('The value you chose for the day is not valid.');
        }
        if ((($month >= -1) && ($month <= 31)) == false) {
            $success &= $this->setErrorMessage('The value you chose for the month is not valid.');
        }
        if ((($day_of_week >= -1) && ($day_of_week < 7)) == false) {
            $success &= $this->setErrorMessage('The value you chose for the day of the week is not valid.');
        }

        return $success;
    }

    /**
     * Validate that a task URL belongs to the current shop domain
     *
     * @param string $task Task URL to validate
     *
     * @return bool True if valid, false otherwise
     */
    protected static function isTaskURLValid($task)
    {
        $task = urlencode($task);
        $shop_url = urlencode(Tools::getShopDomain(true, true) . __PS_BASE_URI__);
        $shop_url_ssl = urlencode(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__);

        return (strpos($task, $shop_url) === 0) || (strpos($task, $shop_url_ssl) === 0);
    }

    /**
     * Add an error message to the errors array
     *
     * @param string $message Error message to add
     *
     * @return bool Always returns false
     */
    protected function setErrorMessage($message)
    {
        $this->_errors[] = $this->l($message);

        return false;
    }

    /**
     * Add a success message to the successes array
     *
     * @param string $message Success message to add
     *
     * @return bool Always returns true
     */
    protected function setSuccessMessage($message)
    {
        $this->_successes[] = $this->l($message);

        return true;
    }

    /**
     * Add a warning message to the warnings array
     *
     * @param string $message Warning message to add
     *
     * @return bool Always returns false
     */
    protected function setWarningMessage($message)
    {
        $this->_warnings[] = $this->l($message);

        return false;
    }

    /**
     * Process the deletion of a cron job
     *
     * @param int $id_cronjob Cron job ID
     *
     * @return void Redirects to module configuration page
     */
    protected function postProcessDeleteCronJob($id_cronjob)
    {
        $id_cronjob = Tools::getValue('id_cronjob');
        $table_name = _DB_PREFIX_ . bqSQL($this->name);

        $select_query = sprintf(
            "SELECT `id_module` FROM `%s` WHERE `id_cronjob` = '%s'",
            $table_name,
            (int) $id_cronjob
        );
        $id_module = Db::getInstance()->getValue($select_query);

        if ((bool) $id_module == false) {
            Db::getInstance()->execute(sprintf(
                "DELETE FROM `%s` WHERE `id_cronjob` = '%s'",
                $table_name,
                (int) $id_cronjob
            ));
        } else {
            Db::getInstance()->execute(sprintf(
                "UPDATE `%s` SET `active` = FALSE WHERE `id_cronjob` = '%s'",
                $table_name,
                (int) $id_cronjob
            ));
        }

        return Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name
            . '&token=' . Tools::getAdminTokenLite('AdminModules'));
    }

    /**
     * Register a module's cron hook in the database
     *
     * @param int $id_module Module ID
     *
     * @return bool True if successful, false otherwise
     */
    protected function registerModuleHook($id_module)
    {
        $module = Module::getInstanceById($id_module);
        $id_shop = (int) Context::getContext()->shop->id;
        $id_shop_group = (int) Context::getContext()->shop->id_shop_group;

        if (is_callable([$module, 'getCronFrequency']) == true) {
            $frequency = $module->getCronFrequency();

            $query = 'INSERT INTO ' . _DB_PREFIX_ . bqSQL($this->name) . '
                (`id_module`, `hour`, `day`, `month`, `day_of_week`, `active`, `id_shop`, `id_shop_group`)
                VALUES (\'' . $id_module . '\', \'' . $frequency['hour'] . '\', \'' . $frequency['day'] . '\',
                    \'' . $frequency['month'] . '\', \'' . $frequency['day_of_week'] . '\',
                    TRUE, ' . $id_shop . ', ' . $id_shop_group . ')';
        } else {
            $query = 'INSERT INTO ' . _DB_PREFIX_ . bqSQL($this->name) . '
                (`id_module`, `active`, `id_shop`, `id_shop_group`)
                VALUES (' . $id_module . ', FALSE, ' . $id_shop . ', ' . $id_shop_group . ')';
        }

        return Db::getInstance()->execute($query);
    }

    /**
     * Unregister a module's cron hook from the database
     *
     * @param int $id_module Module ID
     *
     * @return bool True if successful, false otherwise
     */
    protected function unregisterModuleHook($id_module)
    {
        $table_name = _DB_PREFIX_ . bqSQL($this->name);

        return Db::getInstance()->execute(sprintf(
            "DELETE FROM `%s` WHERE `id_module` = '%s'",
            $table_name,
            (int) $id_module
        ));
    }
}
