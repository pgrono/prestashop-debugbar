<?php

namespace PrestaSoft\PrestaShopDebugBar;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Configuration;
use Context;
use Cookie;
use Db;
use Employee;
use Exception;
use Hook;
use Language;
use psoft_debugbar;
use Smarty_Internal_Template;
use Tools;
use Validate;

class DebugBar
{
    protected $module;
    protected $context;
    protected $employeeChecked = false;
    protected $employee = false;
    protected $requestStarted = false;

    public function __construct($module)
    {
        $this->module = $module;
        $this->context = Context::getContext();
    }

    public function beginRequest()
    {
        if ($this->requestStarted) {
            return (bool) $this->employee;
        }

        $this->requestStarted = true;
        if (!(int) Configuration::get(psoft_debugbar::CONFIG_ENABLED) || !$this->getFrontendEmployee()) {
            return false;
        }

        if (!headers_sent()) {
            header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }

        return true;
    }

    public function render()
    {
        if (!$this->beginRequest()) {
            return '';
        }

        $data = $this->collectData();
        $this->context->smarty->assign(array(
            'psoft_debugbar' => $data,
        ));

        return $this->module->display(
            $this->module->getLocalPath() . $this->module->name . '.php',
            'views/templates/hook/debugbar.tpl'
        );
    }

    public function addBackOfficeAssets()
    {
        if (Tools::getValue('configure') !== $this->module->name) {
            return;
        }

        $this->context->controller->addJS('https://cdn.jsdelivr.net/npm/keen-slider@6.8.5/keen-slider.min.js');
        $this->context->controller->addCSS('https://cdn.jsdelivr.net/npm/keen-slider@6.8.5/keen-slider.min.css');
    }

    public function modulesSlider()
    {
        if ((int) Configuration::get('PSOFT_HIDE_MODULES_SLIDER')) {
            return '<form method="post" style="text-align:center;margin:20px 0;"><button type="submit" name="submitShowSlider" value="1" class="btn btn-default btn-sm"><i class="icon-eye"></i> ' . $this->module->l('Show modules carousel', 'psoft_debugbar') . '</button></form>';
        }

        $lang = $this->context->language->iso_code;
        $json = 'https://prestasoft.pl/modules/modules_rotator/json.php?lang=' . rawurlencode($lang) . '&module_from=' . rawurlencode($this->module->name);
        $data = false;

        if (function_exists('curl_init')) {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $json);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_TIMEOUT, 5);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 3);
            $data = curl_exec($curl);
            curl_close($curl);
        }

        if (!$data) {
            return '';
        }

        $modules = json_decode($data, true);
        if (!is_array($modules)) {
            return '';
        }

        $this->context->smarty->assign(array('ps_modules' => $modules));

        return $this->module->display(
            $this->module->getLocalPath() . $this->module->name . '.php',
            'views/templates/admin/modules_slider.tpl'
        );
    }

    protected function collectData()
    {
        $limit = (int) Configuration::get(psoft_debugbar::CONFIG_LIMIT);
        if ($limit < 10 || $limit > 300) {
            $limit = 100;
        }

        $showHooks = (bool) Configuration::get(psoft_debugbar::CONFIG_HOOKS);
        $showHookTiming = $showHooks
            && (bool) Configuration::get(psoft_debugbar::CONFIG_HOOK_TIMING)
            && method_exists('Hook', 'getPsoftDebugbarHookCollection');
        $hooks = $showHooks ? $this->getExecutedHooks($showHookTiming) : array();
        $templates = Configuration::get(psoft_debugbar::CONFIG_TEMPLATES) ? $this->getLoadedTemplates() : array();
        $queryData = Configuration::get(psoft_debugbar::CONFIG_QUERIES) ? $this->getQueries() : array(
            'queries' => array(),
            'total' => 0,
            'truncated' => 0,
        );
        $controller = isset($this->context->controller) && is_object($this->context->controller)
            ? get_class($this->context->controller)
            : '';
        $page = isset($this->context->controller->php_self)
            ? (string) $this->context->controller->php_self
            : '';
        $employee = $this->getFrontendEmployee();
        $employeeLanguage = $employee && isset($employee->id_lang)
            ? new Language((int) $employee->id_lang)
            : false;
        $employeeLanguageIso = Validate::isLoadedObject($employeeLanguage)
            ? strtolower((string) $employeeLanguage->iso_code)
            : '';
        $authorIsPolish = $employeeLanguageIso === 'pl';
        $authorText = $this->getAuthorText($employeeLanguageIso, $employeeLanguage);

        return array(
            'show_queries' => (bool) Configuration::get(psoft_debugbar::CONFIG_QUERIES),
            'show_hooks' => $showHooks,
            'show_hook_timing' => $showHookTiming,
            'show_templates' => (bool) Configuration::get(psoft_debugbar::CONFIG_TEMPLATES),
            'show_performance' => (bool) Configuration::get(psoft_debugbar::CONFIG_PERFORMANCE),
            'show_context' => (bool) Configuration::get(psoft_debugbar::CONFIG_CONTEXT),
            'query_count' => $queryData['total'],
            'queries' => $queryData['queries'],
            'queries_truncated' => $queryData['truncated'],
            'hook_count' => count($hooks),
            'hooks' => array_slice($hooks, 0, $limit),
            'hooks_truncated' => max(0, count($hooks) - $limit),
            'template_count' => count($templates),
            'templates' => array_slice($templates, 0, $limit),
            'templates_truncated' => max(0, count($templates) - $limit),
            'duration' => $this->getDuration(),
            'memory' => $this->formatBytes(memory_get_usage(true)),
            'peak_memory' => $this->formatBytes(memory_get_peak_usage(true)),
            'controller' => $controller,
            'page' => $page,
            'prestashop' => defined('_PS_VERSION_') ? _PS_VERSION_ : '',
            'php' => PHP_VERSION,
            'shop' => isset($this->context->shop->name) ? (string) $this->context->shop->name : '',
            'language' => isset($this->context->language->iso_code) ? (string) $this->context->language->iso_code : '',
            'author_is_polish' => $authorIsPolish,
            'author_text' => $authorText,
        );
    }

    protected function getAuthorText($employeeLanguageIso, $employeeLanguage)
    {
        $strings = array(
            'title' => 'Module author: Piotr Grono / PrestaSoft.pl',
            'description' => 'I create modules and tools for PrestaShop.',
            'site' => 'Visit PrestaAddons.com',
            'coffee' => 'Buy me a coffee',
            'thanks' => 'If the Debug Bar helps your work, you can optionally buy me a coffee as a thank-you.',
        );
        $supportedLanguages = array('pl', 'en', 'de', 'fr', 'es', 'cs', 'it', 'pt', 'nl', 'da');
        if (!in_array($employeeLanguageIso, $supportedLanguages, true)
            || !Validate::isLoadedObject($employeeLanguage)) {
            return $strings;
        }

        $previousLanguage = $this->context->language;
        $this->context->language = $employeeLanguage;
        $strings = array(
            'title' => $this->module->l('Module author: Piotr Grono / PrestaSoft.pl', 'debugbar'),
            'description' => $this->module->l('I create modules and tools for PrestaShop.', 'debugbar'),
            'site' => $employeeLanguageIso === 'pl'
                ? $this->module->l('Visit PrestaSoft.pl', 'debugbar')
                : $this->module->l('Visit PrestaAddons.com', 'debugbar'),
            'coffee' => $this->module->l('Buy me a coffee', 'debugbar'),
            'thanks' => $this->module->l(
                'If the Debug Bar helps your work, you can optionally buy me a coffee as a thank-you.',
                'debugbar'
            ),
        );
        $this->context->language = $previousLanguage;

        return $strings;
    }

    protected function getQueryCount()
    {
        try {
            $rows = Db::getInstance()->executeS('SHOW SESSION STATUS LIKE "Questions"');
            if (is_array($rows) && isset($rows[0]['Value'])) {
                return max(0, (int) $rows[0]['Value'] - 1);
            }
        } catch (Exception $e) {
            return null;
        }

        return null;
    }

    protected function getQueries()
    {
        if (method_exists('Db', 'getPsoftDebugbarCollection')) {
            $data = Db::getPsoftDebugbarCollection();
            if (is_array($data) && isset($data['queries'], $data['total'], $data['truncated'])) {
                return $data;
            }
        }

        return array(
            'queries' => array(),
            'total' => $this->getQueryCount(),
            'truncated' => 0,
        );
    }

    protected function getExecutedHooks($includeTiming)
    {
        if (!class_exists('Hook') || !property_exists('Hook', 'executed_hooks')) {
            return array();
        }

        $hookNames = array_values(array_unique(array_filter(Hook::$executed_hooks)));
        natcasesort($hookNames);
        $timings = $includeTiming ? Hook::getPsoftDebugbarHookCollection() : array();
        $hooks = array();

        foreach ($hookNames as $hookName) {
            $hasTiming = isset($timings[$hookName]);
            $hooks[] = array(
                'name' => $hookName,
                'timed' => $hasTiming,
                'duration' => $hasTiming
                    ? number_format((float) $timings[$hookName]['duration'], 2, '.', '')
                    : null,
                'calls' => $hasTiming ? (int) $timings[$hookName]['calls'] : 0,
            );
        }

        return $hooks;
    }

    protected function getLoadedTemplates()
    {
        $templates = array();

        if (class_exists('Smarty_Internal_Template', false)
            && property_exists('Smarty_Internal_Template', 'tplObjCache')) {
            foreach (Smarty_Internal_Template::$tplObjCache as $template) {
                if (!is_object($template)) {
                    continue;
                }

                try {
                    if (isset($template->source->filepath) && $template->source->filepath) {
                        $this->addTemplate($templates, $template->source->filepath);
                    } elseif (isset($template->source->name) && $template->source->name) {
                        $this->addTemplate($templates, $template->source->name);
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        }

        foreach (get_included_files() as $compiledFile) {
            if (!preg_match('/\.tpl\.php$/i', $compiledFile)) {
                continue;
            }

            $header = @file_get_contents($compiledFile, false, null, 0, 2048);
            if ($header && preg_match('/from [\'\"]([^\'\"]+\.tpl)[\'\"]/i', $header, $match)) {
                $this->addTemplate($templates, $match[1]);
            }
        }

        $templates = array_values($templates);
        natcasesort($templates);

        return array_values($templates);
    }

    protected function addTemplate(&$templates, $path)
    {
        $path = str_replace('\\', '/', (string) $path);
        if (strpos($path, 'file:') === 0) {
            $path = substr($path, 5);
        }
        $root = str_replace('\\', '/', _PS_ROOT_DIR_) . '/';
        if (strpos($path, $root) === 0) {
            $path = substr($path, strlen($root));
        }
        if (!preg_match('/\.tpl$/i', $path)) {
            return;
        }

        $templates[$path] = $path;
    }

    protected function getDuration()
    {
        $start = isset($_SERVER['REQUEST_TIME_FLOAT'])
            ? (float) $_SERVER['REQUEST_TIME_FLOAT']
            : (isset($GLOBALS['start_time']) ? (float) $GLOBALS['start_time'] : microtime(true));

        return number_format((microtime(true) - $start) * 1000, 1, '.', '');
    }

    protected function formatBytes($bytes)
    {
        $bytes = max(0, (float) $bytes);
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2, '.', '') . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, '.', '') . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1, '.', '') . ' KB';
        }

        return (int) $bytes . ' B';
    }

    protected function getFrontendEmployee()
    {
        if ($this->employeeChecked) {
            return $this->employee;
        }

        $this->employeeChecked = true;
        if (defined('_PS_ADMIN_DIR_') || PHP_SAPI === 'cli') {
            return false;
        }

        $adminCookie = new Cookie('psAdmin', '', null, null, false, (bool) Configuration::get('PS_SSL_ENABLED'));
        if (method_exists($adminCookie, 'disallowWriting')) {
            $adminCookie->disallowWriting();
        }

        $idEmployee = isset($adminCookie->id_employee) ? (int) $adminCookie->id_employee : 0;
        $password = isset($adminCookie->passwd) ? (string) $adminCookie->passwd : '';
        if (!$idEmployee || !$password || !Employee::checkPassword($idEmployee, $password)) {
            return false;
        }

        if (method_exists($adminCookie, 'isSessionAlive') && !$adminCookie->isSessionAlive()) {
            return false;
        }

        if (isset($adminCookie->remote_addr)
            && Configuration::get('PS_COOKIE_CHECKIP')
            && (int) $adminCookie->remote_addr !== (int) ip2long(Tools::getRemoteAddr())) {
            return false;
        }

        $employee = new Employee($idEmployee);
        if (!Validate::isLoadedObject($employee) || !(int) $employee->active) {
            return false;
        }

        if (method_exists($employee, 'hasAuthOnShop')
            && !$employee->isSuperAdmin()
            && !$employee->hasAuthOnShop((int) $this->context->shop->id)) {
            return false;
        }

        $this->employee = $employee;

        return $employee;
    }
}
