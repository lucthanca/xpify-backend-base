<?php
declare(strict_types=1);

namespace Xpify\Core\Plugin\Config;

class RemoveUnwantedConfig
{
    protected array $unwantedTabs = ['customer', 'sales', 'catalog', 'avada'];
    const UNWANTED_SECTIONS = [
        'newrelicreporting',
        'cms',
        'reports',
        'contact',
        'trans_email',
        'currency',
        'design',
    ];

    public function beforeMerge($subject, $config)
    {
        if (!isset($config['config']['system'])) {
            return [$config];
        }
        $sections = &$config['config']['system']['sections'];

        foreach ($this->unwantedTabs as $unwantedSection) {
            if (isset($sections[$unwantedSection])) {
                unset($sections[$unwantedSection]);
            }
            foreach ($sections as $key => &$section) {
                $tabId = $section['tab'] ?? null;
                if ($tabId === $unwantedSection) {
                    unset($sections[$key]);
                }
            }
            if (isset($config['config']['system']['tabs'][$unwantedSection])) {
                unset($config['config']['system']['tabs'][$unwantedSection]);
            }
        }

        foreach (self::UNWANTED_SECTIONS as $unwantedSection) {
            if (isset($sections[$unwantedSection])) {
                unset($sections[$unwantedSection]);
            }
        }
//        dd($config);
        return [$config];
    }
}
