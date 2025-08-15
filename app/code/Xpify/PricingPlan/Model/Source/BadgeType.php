<?php
declare(strict_types=1);

namespace Xpify\PricingPlan\Model\Source;

use Magento\Framework\Data\OptionSourceInterface as IOptionSource;

class BadgeType implements IOptionSource
{
    const BADGE_TYPE_TONE_INFO = 'info';
    const BADGE_TYPE_TONE_SUCCESS = 'success';
    const BADGE_TYPE_TONE_WARNING = 'warning';
    const BADGE_TYPE_TONE_CRITICAL = 'critical';
    const BADGE_TYPE_TONE_ATTENTION = 'attention';
    const BADGE_TYPE_TONE_NEW = 'new';
    const BADGE_TYPE_TONE_MAGIC = 'magic';
    const BADGE_TYPE_TONE_INFO_STRONG = 'info-strong';
    const BADGE_TYPE_TONE_SUCCESS_STRONG = 'success-strong';
    const BADGE_TYPE_TONE_WARNING_STRONG = 'warning-strong';
    const BADGE_TYPE_TONE_CRITICAL_STRONG = 'critical-strong';
    const BADGE_TYPE_TONE_ATTENTION_STRONG = 'attention-strong';
    const BADGE_TYPE_TONE_READ_ONLY = 'read-only';
    const BADGE_TYPE_TONE_ENABLED = 'enabled';

    // mapping for badge types, please return the label in friendly format
    private static array $mapping = [
        self::BADGE_TYPE_TONE_INFO => 'Info',
        self::BADGE_TYPE_TONE_SUCCESS => 'Success',
        self::BADGE_TYPE_TONE_WARNING => 'Warning',
        self::BADGE_TYPE_TONE_CRITICAL => 'Critical',
        self::BADGE_TYPE_TONE_ATTENTION => 'Attention',
        self::BADGE_TYPE_TONE_NEW => 'New',
        self::BADGE_TYPE_TONE_MAGIC => 'Magic',
        self::BADGE_TYPE_TONE_INFO_STRONG => 'Info (Strong)',
        self::BADGE_TYPE_TONE_SUCCESS_STRONG => 'Success (Strong)',
        self::BADGE_TYPE_TONE_WARNING_STRONG => 'Warning (Strong)',
        self::BADGE_TYPE_TONE_CRITICAL_STRONG => 'Critical (Strong)',
        self::BADGE_TYPE_TONE_ATTENTION_STRONG => 'Attention (Strong)',
        self::BADGE_TYPE_TONE_READ_ONLY => 'Read Only',
        self::BADGE_TYPE_TONE_ENABLED => 'Enabled',
    ];

    /**
     * Return options as value-label array
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (self::$mapping as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => __($label)
            ];
        }
        return $options;
    }
}
