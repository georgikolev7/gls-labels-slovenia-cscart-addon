<?php

if (!defined('BOOTSTRAP')) { die('Access denied'); }

if ($mode == 'print_packing_slip') {

    fn_gls_print_packing_slip($_GET['order_id']);
    exit();
}