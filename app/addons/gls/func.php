<?php

use Tygh\Registry;
use Tygh\Settings;

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

function fn_gls_print_packing_slip($order_id)
{
    $view = Registry::get('view');
    $html = '';
    
    $settings = fn_gls_get_settings();
    $url = 'https://connect.gls-slovenia.com/webservices/soap_server.php?wsdl&ver=16.12.15.01';
    
    $order_info = fn_get_order_info($order_id, false, true, false, true);
    
    if (empty($order_info)) {
        return false;
    }
    
    $client = new \SoapClient($url);
    
    $sender_info = $order_info['product_groups'][0]['package_info']['origination'];
    
    $args = array(
        'username' => $settings['username'],
        'password' => $settings['password'],
        'senderid' => $settings['client_id'],
        'sender_name' => $sender_info['name'],
        'sender_address' => $sender_info['address'],
        'sender_city' => $sender_info['city'],
        'sender_zipcode' => $sender_info['zipcode'],
        'sender_country' => $sender_info['country'],
        'sender_contact' => '',
        'sender_phone' => '',
        'sender_email' => '',
        'consig_name' => $order_info['firstname'] . ' ' . $order_info['lastname'],
        'consig_address' => $order_info['b_address'],
        'consig_city' => $order_info['b_city'],
        'consig_zipcode' => $order_info['b_zipcode'],
        'consig_country' => $order_info['b_country'],
        'consig_contact' => '',
        'consig_phone' => $order_info['b_phone'],
        'consig_email' => $order_info['email'],
        'services' => '',
        'content' => '',
        'clientref' => '',
        'codamount' => $order_info['total'],
        'codref' => '',
        'hash' => 'nohash',
        'printertemplate' => 'A6',
        'customlabel' => false,
        'printit' => true,
        'timestamp' => strtotime('now'),
        'pcount' => 1,
        'pickupdate' => date('Y-m-d')
    );
        
    $args['hash'] = fn_gls_get_hash($args);
    
    $response = $client->__soapCall('printlabel', $args);
    $response = json_decode(json_encode($response), true);
    
    if (!isset($response['successfull'])) {
        print_r($response);
        return false;
    }
    
    $html = base64_decode($response['pdfdata']);
    
    Pdf::render($html, __('packing_slip') . '-' . $order_id);
}

function fn_gls_get_hash($data) 
{
    $hashBase = '';
    
    foreach($data as $key => $value) {
        if ($key != 'services'
        && $key != 'hash'
        && $key != 'timestamp'
        && $key != 'printit'
        && $key != 'printertemplate'
        && $key != 'customlabel') {
            $hashBase .= $value;
        }
    }
    
    return sha1($hashBase);
} 

/**
 * @param $lang_code
 * @return mixed
 */
function fn_gls_get_settings($lang_code = DESCR_SL)
{
    $settings = Settings::instance()->getValues('gls', 'ADDON');
    return $settings['general'];
}

function fn_gls_install()
{
    $services = [
        ['code' => 'T12', 'name' => 'GLS Express Service'],
        ['code' => 'PSS', 'name' => 'Pick&Ship Service'],
        ['code' => 'PRS', 'name' => 'Pick&Return Service'],
        ['code' => 'FDS', 'name' => 'FlexDelivery Service'],
        ['code' => 'FSS', 'name' => 'FlexDeivery SMS Service']
    ];
    
    foreach ($services as $service) {
        $service_data = $service;
        $service_data['module'] = 'gls';
        $service_data['status'] = 'A';
        unset($service_data['name']);
        
        $service_id = db_query("INSERT INTO `?:shipping_services` ?e", $service_data);
        
        $service_description = [
            'service_id' => $service_id,
            'description' => $service['name'],
            'lang_code' => DESCR_SL
        ];
        
        db_query("INSERT INTO `?:shipping_service_descriptions` ?e", $service_description);
    }
}

function fn_gls_uninstall()
{
    $services = db_get_array("SELECT * FROM `?:shipping_services` WHERE module = ?s", 'gls');
    foreach ($services as $service) {
        db_query("DELETE FROM `?:shipping_services` WHERE service_id = ?i", $service['service_id']);
        db_query("DELETE FROM `?:shipping_service_descriptions` WHERE `service_id` = ?i", $service['service_id']);
    }
}
