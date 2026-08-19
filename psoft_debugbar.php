<?php
/**
 * @author    PrestaSoft.pl
 * @copyright PrestaSoft.pl
 * @license   MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/PsoftDebugbar.php';

class psoft_debugbar extends Module
{
    const CONFIG_ENABLED = 'PSOFT_DEBUGBAR_ENABLED';
    const CONFIG_QUERIES = 'PSOFT_DEBUGBAR_QUERIES';
    const CONFIG_HOOKS = 'PSOFT_DEBUGBAR_HOOKS';
    const CONFIG_HOOK_TIMING = 'PSOFT_DEBUGBAR_HOOK_TIMING';
    const CONFIG_TEMPLATES = 'PSOFT_DEBUGBAR_TEMPLATES';
    const CONFIG_PERFORMANCE = 'PSOFT_DEBUGBAR_PERFORMANCE';
    const CONFIG_CONTEXT = 'PSOFT_DEBUGBAR_CONTEXT';
    const CONFIG_LIMIT = 'PSOFT_DEBUGBAR_LIMIT';

    public $backOfficeHeaderHookName;

    protected $debugbar;
    protected $frontPrepared = false;
    protected $frontAllowed = false;
    protected $assetsRegistered = false;

    public function __construct()
    {
        $this->name = 'psoft_debugbar';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'PrestaSoft.pl';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = array(
            'min' => '1.7.0.0',
            'max' => _PS_VERSION_,
        );

        parent::__construct();

        $this->displayName = $this->l('PrestaShop Debug Bar');
        $this->description = $this->l('Displays a lightweight diagnostic bar on the storefront for logged-in administrators.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall the module?');
        $this->backOfficeHeaderHookName = version_compare(_PS_VERSION_, '1.7.7.0', '<')
            ? 'backOfficeHeader'
            : 'displayBackOfficeHeader';
        $this->debugbar = new PsoftDebugbar($this);
    }

    public function install()
    {
        if (Module::isInstalled('psoft_devbar')) {
            $this->_errors[] = $this->l('Uninstall psoft_devbar before installing psoft_debugbar.');

            return false;
        }

        $result = parent::install()
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayFooter')
            && $this->registerHook('actionDispatcher')
            && $this->registerHook($this->backOfficeHeaderHookName);

        if ($result && version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            $result = $this->registerHook('actionFrontControllerSetMedia');
        }

        return $result && $this->installConfiguration();
    }

    public function uninstall()
    {
        foreach ($this->getConfigurationKeys() as $key) {
            Configuration::deleteByName($key);
        }

        return parent::uninstall();
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitHideSlider')) {
            Configuration::updateValue('PSOFT_HIDE_MODULES_SLIDER', 1);
        }
        if (Tools::isSubmit('submitShowSlider')) {
            Configuration::updateValue('PSOFT_HIDE_MODULES_SLIDER', 0);
        }
        if (Tools::isSubmit('submit' . $this->name)) {
            foreach ($this->getSwitchConfiguration() as $key => $label) {
                Configuration::updateValue($key, (int) Tools::getValue($key));
            }

            $limit = (int) Tools::getValue(self::CONFIG_LIMIT);
            if ($limit < 10) {
                $limit = 10;
            } elseif ($limit > 300) {
                $limit = 300;
            }
            Configuration::updateValue(self::CONFIG_LIMIT, $limit);
            $output .= $this->displayConfirmation($this->l('Settings have been saved.'));
        }

        return $output . $this->renderForm() . $this->debugbar->modulesSlider();
    }

    public function hookActionFrontControllerSetMedia()
    {
        if ($this->prepareFront()) {
            $this->registerFrontAssets();
        }
    }

    public function hookActionDispatcher()
    {
        if (!$this->prepareFront()) {
            return;
        }

        $limit = (int) Configuration::get(self::CONFIG_LIMIT);
        if ($limit < 10 || $limit > 300) {
            $limit = 100;
        }

        if (method_exists('Db', 'startPsoftDebugbarCollection')) {
            Db::startPsoftDebugbarCollection($limit);
        }

        if (Configuration::get(self::CONFIG_HOOKS)
            && Configuration::get(self::CONFIG_HOOK_TIMING)
            && method_exists('Hook', 'startPsoftDebugbarHookCollection')) {
            Hook::startPsoftDebugbarHookCollection('actionDispatcher');
        }
    }

    public function hookDisplayHeader()
    {
        if (!$this->prepareFront()) {
            return '';
        }

        if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            $this->registerFrontAssets();

            return '';
        }

        return '<link rel="stylesheet" href="' . htmlspecialchars($this->_path . 'views/css/front.css', ENT_QUOTES, 'UTF-8') . '" type="text/css" media="all">'
            . '<script defer src="' . htmlspecialchars($this->_path . 'views/js/front.js', ENT_QUOTES, 'UTF-8') . '"></script>';
    }

    public function hookDisplayFooter()
    {
        if (!$this->prepareFront()) {
            return '';
        }

        return $this->debugbar->render();
    }

    public function hookBackOfficeHeader()
    {
        $this->debugbar->addBackOfficeAssets();
    }

    public function hookDisplayBackOfficeHeader()
    {
        $this->hookBackOfficeHeader();
    }

    protected function prepareFront()
    {
        if ($this->frontPrepared) {
            return $this->frontAllowed;
        }

        $this->frontPrepared = true;
        $this->frontAllowed = $this->debugbar->beginRequest();

        return $this->frontAllowed;
    }

    protected function registerFrontAssets()
    {
        if ($this->assetsRegistered) {
            return;
        }

        $this->assetsRegistered = true;
        if (method_exists($this->context->controller, 'registerStylesheet')) {
            $this->context->controller->registerStylesheet(
                'module-' . $this->name . '-style',
                'modules/' . $this->name . '/views/css/front.css',
                array('media' => 'all', 'priority' => 200)
            );
            $this->context->controller->registerJavascript(
                'module-' . $this->name . '-script',
                'modules/' . $this->name . '/views/js/front.js',
                array('position' => 'bottom', 'priority' => 200)
            );

            return;
        }

        $this->context->controller->addCSS($this->_path . 'views/css/front.css', 'all');
        $this->context->controller->addJS($this->_path . 'views/js/front.js');
    }

    protected function installConfiguration()
    {
        foreach ($this->getSwitchConfiguration() as $key => $label) {
            if (!Configuration::hasKey($key)) {
                $legacyKey = str_replace('PSOFT_DEBUGBAR_', 'PSOFT_DEVBAR_', $key);
                $value = Configuration::hasKey($legacyKey) ? (int) Configuration::get($legacyKey) : 1;
                Configuration::updateValue($key, $value);
            }
        }
        if (!Configuration::hasKey(self::CONFIG_LIMIT)) {
            $legacyLimit = Configuration::hasKey('PSOFT_DEVBAR_LIMIT')
                ? (int) Configuration::get('PSOFT_DEVBAR_LIMIT')
                : 100;
            Configuration::updateValue(self::CONFIG_LIMIT, max(10, min(300, $legacyLimit)));
        }

        return true;
    }

    protected function getConfigurationKeys()
    {
        return array_merge(array_keys($this->getSwitchConfiguration()), array(self::CONFIG_LIMIT));
    }

    public function getSwitchConfiguration()
    {
        return array(
            self::CONFIG_ENABLED => $this->l('Enable the debug bar'),
            self::CONFIG_QUERIES => $this->l('Show SQL query count and details'),
            self::CONFIG_HOOKS => $this->l('Show executed hooks'),
            self::CONFIG_HOOK_TIMING => $this->l('Measure hook execution time'),
            self::CONFIG_TEMPLATES => $this->l('Show loaded TPL templates'),
            self::CONFIG_PERFORMANCE => $this->l('Show execution time and memory usage'),
            self::CONFIG_CONTEXT => $this->l('Show page and environment context'),
        );
    }

    protected function renderForm()
    {
        $inputs = array();
        foreach ($this->getSwitchConfiguration() as $key => $label) {
            $inputs[] = array(
                'type' => 'switch',
                'label' => $label,
                'name' => $key,
                'is_bool' => true,
                'values' => array(
                    array('id' => $key . '_on', 'value' => 1, 'label' => $this->l('Yes')),
                    array('id' => $key . '_off', 'value' => 0, 'label' => $this->l('No')),
                ),
            );
        }
        $inputs[] = array(
            'type' => 'text',
            'label' => $this->l('Maximum number of entries per section'),
            'name' => self::CONFIG_LIMIT,
            'class' => 'fixed-width-sm',
            'desc' => $this->l('Allowed range: 10-300.'),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = (int) $this->context->language->id;
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit' . $this->name;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name
            . '&tab_module=' . $this->tab
            . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        foreach ($this->getSwitchConfiguration() as $key => $label) {
            $helper->fields_value[$key] = (int) Configuration::get($key);
        }
        $helper->fields_value[self::CONFIG_LIMIT] = (int) Configuration::get(self::CONFIG_LIMIT);

        return $helper->generateForm(array(array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Debug bar settings'),
                    'icon' => 'icon-dashboard',
                ),
                'description' => $this->l('The bar works without PrestaShop debug mode and is visible only during an active administrator session.'),
                'input' => $inputs,
                'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                ),
            ),
        )));
    }
}
