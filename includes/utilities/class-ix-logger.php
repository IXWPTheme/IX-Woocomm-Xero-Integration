<?php
class IX_Logger {
    
    public static function log($message, $level = 'info', $source = 'ix-woo-xero') {
        if (!function_exists('wc_get_logger')) {
            return;
        }
        
        $logger = wc_get_logger();
        $context = ['source' => $source];
        
        switch ($level) {
            case 'error':
                $logger->error($message, $context);
                break;
            case 'warning':
                $logger->warning($message, $context);
                break;
            case 'debug':
                $logger->debug($message, $context);
                break;
            case 'info':
            default:
                $logger->info($message, $context);
                break;
        }
    }
}