<?php
/**
 * Copyright (C) 2023 Pixel Développement
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Addon\Theme\ThemeProviderInterface;
use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class Pixel_plausible extends Module
{
    /**
     * Module's constructor.
     */
    public function __construct()
    {
        $this->name = 'pixel_plausible';
        $this->version = '1.0.0';
        $this->author = 'Pixel Open';
        $this->tab = 'analytics_stats';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans(
            'Plausible',
            [],
            'Modules.Pixelplausible.Admin'
        );
        $this->description = $this->trans(
            'Add Plausible Analytics in Prestashop.',
            [],
            'Modules.Pixelplausible.Admin'
        );
        $this->ps_versions_compliancy = [
            'min' => '1.7.6.0',
            'max' => _PS_VERSION_,
        ];

        $tabNames = [];
        foreach (Language::getLanguages() as $lang) {
            $tabNames[$lang['locale']] = 'Plausible';
        }

        $this->tabs = [
            [
                'route_name' => 'admin_plausible_stats',
                'class_name' => 'AdminPixelPlausible',
                'visible' => true,
                'name' => $tabNames,
                'parent_class_name' => 'AdminStats',
                'wording' => 'Plausible',
                'wording_domain' => 'Modules.Pixelplausible.Admin',
            ],
        ];
    }

    /***************************/
    /** MODULE INITIALIZATION **/
    /***************************/

    /**
     * Install the module
     *
     * @return bool
     */
    public function install(): bool
    {
        return parent::install() &&
            $this->registerHook('displayHeader') &&
            $this->registerHook('actionFrontControllerSetMedia') &&
            $this->registerHook('displayBeforeBodyClosingTag') &&
            $this->registerHook('displayBackOfficeHeader');
    }

    /**
     * Uninstall the module
     *
     * @return bool
     */
    public function uninstall(): bool
    {
        return parent::uninstall() && $this->deleteConfigurations();
    }

    /**
     * Use the new translation system
     *
     * @return bool
     */
    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    /***********/
    /** HOOKS **/
    /***********/

    /**
     * Add the script in the head
     *
     * @return string
     */
    public function hookDisplayHeader(): string
    {
        if (!Configuration::get('PLAUSIBLE_ENABLED')) {
            return '';
        }

        return $this->fetch('module:pixel_plausible/views/templates/script.tpl');
    }

    /**
     * Add the goal triggers before body end
     *
     * @return string
     */
    public function hookDisplayBeforeBodyClosingTag(): string
    {
        if (!$this->isGoalsEnabled()) {
            return '';
        }

        return $this->fetch('module:pixel_plausible/views/templates/goals.tpl');
    }



    /**
     * Add JS on frontend
     *
     * @return void
     */
    public function hookActionFrontControllerSetMedia(): void
    {
        if ($this->isGoalsEnabled()) {
            $this->context->controller->registerJavascript(
                'plausible-goals',
                'modules/' . $this->name . '/views/js/goals.js',
                [
                    'position' => 'head',
                    'priority' => 100,
                ]
            );
        }
    }

    /**
     * Add CSS in the admin controller
     *
     * @return void
     */
    public function hookDisplayBackOfficeHeader(): void
    {
        if ($this->context->controller->controller_name === 'AdminPixelPlausible') {
            $this->context->controller->addCSS($this->getPathUri() . 'views/css/admin/plausible.css');
        }
    }

    /*******************/
    /** CONFIGURATION **/
    /*******************/

    /**
     * Retrieve config fields
     *
     * @return array[]
     */
    protected function getConfigFields(): array
    {
        return [
            'PLAUSIBLE_ENABLED' => [
                'type'     => 'select',
                'label'    => $this->trans('Add JavaScript snippet', [], 'Modules.Pixelplausible.Admin'),
                'name'     => 'PLAUSIBLE_ENABLED',
                'required' => false,
                'options' => [
                    'query' => [
                        [
                            'value' => '0',
                            'name'  => $this->trans('No', [], 'Modules.Pixelplausible.Admin'),
                        ],
                        [
                            'value' => '1',
                            'name'  => $this->trans('Yes', [], 'Modules.Pixelplausible.Admin'),
                        ],
                    ],
                    'id'   => 'value',
                    'name' => 'name',
                ],
                'desc' => $this->trans(
                    'Enable stats by including the Plausible snippet in the &lt;head&gt; of your website.',
                    [],
                    'Modules.Pixelplausible.Admin'
                )
            ],
            'PLAUSIBLE_GOALS' => [
                'type'     => 'select',
                'label'    => $this->trans('Default goals', [], 'Modules.Pixelplausible.Admin'),
                'name'     => 'PLAUSIBLE_GOALS',
                'required' => false,
                'options' => [
                    'query' => [
                        [
                            'value' => '0',
                            'name'  => $this->trans('No', [], 'Modules.Pixelplausible.Admin'),
                        ],
                        [
                            'value' => '1',
                            'name'  => $this->trans('Yes', [], 'Modules.Pixelplausible.Admin'),
                        ],
                    ],
                    'id'   => 'value',
                    'name' => 'name',
                ],
                'desc' => $this->trans(
                    'Enable default goal events: contact, cart, checkout-step-X, order',
                    [],
                    'Modules.Pixelplausible.Admin'
                )
            ],
            'PLAUSIBLE_SHARED_LINK' => [
                'type'     => 'text',
                'label'    => $this->trans('Shared Link', [], 'Modules.Pixelplausible.Admin'),
                'name'     => 'PLAUSIBLE_SHARED_LINK',
                'size'     => 20,
                'required' => false,
                'desc' => $this->trans(
                    'The shared link allows to display stats in the "Statistics > Plausible" menu.',
                    [],
                    'Modules.Pixelplausible.Admin'
                )
            ],
        ];
    }

    /**
     * This method handles the module's configuration page
     *
     * @return string
     */
    public function getContent(): string
    {
        $output = '';

        if (!Configuration::get('PLAUSIBLE_SHARED_LINK')) {
            $message = $this->trans(
                'Create the "shared link" in your Plausible settings for %s: Visibility > Shared links > + New link',
                [Tools::getHttpHost()],
                'Modules.Pixelcloudflareturnstile.Admin'
            );

            $output = '<div class="alert alert-info">' . $message . '</div>';
        }

        if (Tools::isSubmit('submit' . $this->name)) {
            foreach ($this->getConfigFields() as $code => $field) {
                $value = Tools::getValue($field['name']);
                if ($field['required'] && empty($value)) {
                    return $this->displayError(
                        $this->trans('%field% is empty', ['%field%' => $field['label']], 'Modules.Pixelplausible.Admin')
                    ) . $this->displayForm();
                }
                if ($value && ($field['multiple'] ?? false) === true) {
                    $value = join(',', $value);
                }
                Configuration::updateValue($code, $value);
            }

            $output = $this->displayConfirmation($this->trans('Settings updated', [], 'Modules.Pixelplausible.Admin'));
        }

        return $output . $this->displayForm();
    }

    /**
     * Builds the configuration form
     *
     * @return string
     */
    public function displayForm(): string
    {
        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Settings', [], 'Modules.Pixelplausible.Admin'),
                ],
                'input' => $this->getConfigFields(),
                'submit' => [
                    'title' => $this->trans('Save', [], 'Modules.Pixelplausible.Admin'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        $helper = new HelperForm();

        $helper->table = $this->table;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&' . http_build_query(['configure' => $this->name]);
        $helper->submit_action = 'submit' . $this->name;

        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

        foreach ($this->getConfigFields() as $code => $field) {
            $value = Tools::getValue($code, Configuration::get($code));
            if (!is_array($value) && ($field['multiple'] ?? false) === true) {
                $value = explode(',', $value);
            }
            $helper->fields_value[$field['name']] = $value;
        }

        return $helper->generateForm([$form]);
    }

    /**
     * Check if default goals are enabled
     *
     * @return bool
     */
    public function isGoalsEnabled(): bool
    {
        return Configuration::get('PLAUSIBLE_ENABLED') && Configuration::get('PLAUSIBLE_GOALS');
    }

    /**
     * Delete configurations
     *
     * @return bool
     */
    protected function deleteConfigurations(): bool
    {
        foreach ($this->getConfigFields() as $key => $options) {
            Configuration::deleteByName($key);
        }

        return true;
    }
}
