<?php
/*
Plugin Name: Domain Mapping cPanel
Plugin URI:  http://enshrined.co.uk
Description: Add a mapped domain to cPanel as a parked domain
Version:     0.0.1
Author:      Daryll Doyle
Author URI:  http://enshrined.co.uk
Text Domain: domain-mapping-cpanel
 */

require('vendor/xmlapi.php');

if (!class_exists('EnshrinedMappedcPanel')) {

    /**
     * Class EnshrinedMappedcPanel
     */
    class EnshrinedMappedcPanel
    {

        /**
         * @var
         */
        public $cPanel;

        /**
         * @var
         */
        public $host;

        /**
         * @var
         */
        public $username;

        /**
         * @var
         */
        public $password;

        /**
         * @var
         */
        public $options;

        /**
         * Set up the class
         */
        function __construct()
        {
            $this->options = get_site_option('cpanelSettings');

            $this->addMenus();

            if (!$this->options) {
                add_action('network_admin_notices', array($this, 'addNotice'));
                return;
            }

            if (isset($_GET['cPanelSettingsUpdated'])) {
                add_action('network_admin_notices', array($this, 'addUpdated'));
            }

            $this->addHooks();

            $this->host = $this->options['hostname'];
            $this->username = $this->options['username'];
            $this->password = $this->options['password'];
        }

        /**
         * Nag to enter cPanel details
         */
        function addNotice()
        {
            $class = "update-nag";
            $url = network_admin_url() . 'settings.php?page=domain-mapping-cpanel';
            $message = "Please add your cPanel details <a href='{$url}'>here</a>";
            echo "<div class=\"$class\"> <p>$message</p></div>";
        }

        /**
         * Settings were updated
         */
        function addUpdated()
        {
            $class = "updated";
            $message = "Settings updated!</a>";
            echo "<div class=\"$class\"> <p>$message</p></div>";
        }

        /**
         * Get an API Connection
         *
         * @throws Exception
         */
        private function getConnection()
        {
            $this->cPanel = new xmlapi($this->host);
            $this->cPanel->set_port('2083');
            $this->cPanel->set_user($this->username);
            $this->cPanel->set_password($this->password);
        }

        /**
         * Add the menus
         */
        private function addMenus()
        {
            add_action('network_admin_menu', array($this, 'registerAdminMenu'));
            add_action('admin_init', array($this, 'initOptions'));
            add_action('network_admin_edit_cpanelDomainMapping', array($this, 'saveOptions'));
        }

        /**
         * Add the hooks
         */
        private function addHooks()
        {
            add_action('domainmapping_added_domain', array($this, 'addParkedDomain'));
            add_action('domainmapping_deleted_domain', array($this, 'removeParkedDomain'));
        }

        /**
         * Add settings for admin page
         */
        function initOptions()
        {
            register_setting(
                'mappedDomainCpanel', // Option group
                'cpanelSettings' // Option name
            );

            add_settings_section(
                'cpanel', // ID
                'cPanel Settings', // Title
                array($this, 'sectionInfo'), // Callback
                'domain-mapping-cpanel' // Page
            );

            add_settings_field(
                'hostname', // ID
                'Hostname', // Title
                array($this, 'hostnameCallback'), // Callback
                'domain-mapping-cpanel', // Page
                'cpanel' // Section
            );

            add_settings_field(
                'username',
                'Username',
                array($this, 'usernameCallback'),
                'domain-mapping-cpanel',
                'cpanel'
            );

            add_settings_field(
                'password',
                'Password',
                array($this, 'passwordCallback'),
                'domain-mapping-cpanel',
                'cpanel'
            );
        }

        /**
         * Get the settings option array and print one of its values
         */
        public function hostnameCallback()
        {
            printf(
                '<input type="text" id="id_number" name="cpanelSettings[hostname]" value="%s" />',
                isset($this->options['hostname']) ? esc_attr($this->options['hostname']) : ''
            );
        }

        /**
         * Get the settings option array and print one of its values
         */
        public function usernameCallback()
        {
            printf(
                '<input type="text" id="title" name="cpanelSettings[username]" value="%s" />',
                isset($this->options['username']) ? esc_attr($this->options['username']) : ''
            );
        }

        /**
         * Get the settings option array and print one of its values
         */
        public function passwordCallback()
        {
            printf(
                '<input type="password" id="title" name="cpanelSettings[password]" value="%s" />',
                isset($this->options['password']) ? esc_attr($this->options['password']) : ''
            );
        }

        /**
         * Print the section header
         */
        public function sectionInfo()
        {
            print 'Enter your settings below:';
        }

        /**
         * Register the admin menu
         */
        function registerAdminMenu()
        {
            add_submenu_page('settings.php', 'Domain Mapping cPanel', 'Domain Mapping cPanel', 'manage_network_options',
                'domain-mapping-cpanel', array($this, 'renderAdminMenu'));
        }

        /**
         * Render the admin page
         */
        function renderAdminMenu()
        {
            ?>
            <div class="wrap">
                <h2>Your cPanel Settings</h2>

                <form method="post" action="<?php echo network_admin_url(); ?>edit.php?action=cpanelDomainMapping">
                    <?php
                    // This prints out all hidden setting fields
                    settings_fields('mappedDomainCpanel');
                    do_settings_sections('domain-mapping-cpanel');
                    submit_button();
                    ?>
                </form>
            </div>
        <?php
        }

        /**
         * Save the network wide options
         */
        function saveOptions()
        {
            $settings = array();

            foreach ($_POST['cpanelSettings'] as $key => $value) {
                $settings[$key] = sanitize_text_field($value);
            }

            update_site_option('cpanelSettings', $settings);
            wp_redirect(network_admin_url() . 'settings.php?page=domain-mapping-cpanel&cPanelSettingsUpdated=true');
            exit();
        }

        /**
         * Park a domain in cPanel
         *
         * @param $domain
         * @param $blogID
         */
        function addParkedDomain($domain, $blogID)
        {
            $this->getConnection();
            $result = $this->cPanel->park($this->username, $domain, false);
        }

        /**
         * Remove a domain from cPanel
         *
         * @param $domain
         * @param $blogID
         */
        function removeParkedDomain($domain, $blogID)
        {
            $this->getConnection();
            $result = $this->cPanel->unpark($this->username, $domain);
        }

    }
}

$EnshrinedMappedcPanel = new EnshrinedMappedcPanel();
