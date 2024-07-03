<?php
declare(strict_types=1);

namespace Xpify\Core\Plugin;

use Magento\Cron\Model\ConfigInterface as ICronConfig;

class CronConfig
{
    const UNWANTED_JOBS = [
        'system_backup',
        'currency_rates_update',
        'backend_clean_cache',
        'visitor_clean',
        'catalog_index_refresh_price',
        'catalog_product_flat_indexer_store_cleanup',
        'catalog_product_outdated_price_values_cleanup',
        'catalog_product_frontend_actions_flush',
        'catalog_product_attribute_value_synchronize',
        'catalogrule_apply_all',
        'security_clean_admin_expired_sessions',
        'security_clean_password_reset_request_event_records',
        'security_deactivate_expired_users',
        'outdated_authentication_failures_cleanup',
        'messagequeue_clean_outdated_locks',
        'sales_clean_quotes',
        'sales_clean_orders',
        'aggregate_sales_report_order_data',
        'aggregate_sales_report_invoiced_data',
        'aggregate_sales_report_refunded_data',
        'aggregate_sales_report_bestsellers_data',
        'sales_grid_order_async_insert',
        'sales_grid_order_invoice_async_insert',
        'sales_grid_order_shipment_async_insert',
        'sales_grid_order_creditmemo_async_insert',
        'sales_send_order_emails',
        'sales_send_order_invoice_emails',
        'sales_send_order_shipment_emails',
        'sales_send_order_creditmemo_emails',
        'mysqlmq_clean_messages',
        'newsletter_send_all',
        'persistent_clear_expired',
        'catalog_product_alert',
        'aggregate_sales_report_coupons_data',
        'magento_newrelicreporting_cron',
        'aggregate_sales_report_shipment_data',
        'aggregate_sales_report_tax_data',
        'indexer_reindex_all_invalid',
        'indexer_update_all_views',
        'indexer_clean_all_changelogs',
        'consumers_runner',
    ];
    public function afterGetJobs(ICronConfig $subject, $jobs)
    {
        foreach ($jobs as &$groupJobs) {
            $groupJobs = array_filter($groupJobs, function($job) {
                return !in_array($job['name'], self::UNWANTED_JOBS);
            });
        }
        unset($groupJobs);
        return $jobs;
    }
}
