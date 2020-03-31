<?php
/**
 * Diglin GmbH - Switzerland.
 *
 * @author      Sylvain Rayé <support at diglin.com>
 * @category    OroAkeneoBundle
 * @copyright   2020 - Diglin (https://www.diglin.com)
 */

namespace Oro\Bundle\AkeneoBundle\Tools;

final class ParseUpdatedPlaceholder
{
    const UPDATED_PLACEHOLDER = '<updated_at_placeholder>';
    /**
     * @var string
     */
    private $input;
    /**
     * @var \Datetime
     */
    private $updated;

    public function __construct(string $input, ?\Datetime $updated = null)
    {
        $this->input = $input;
        $this->updated = $updated ?? new \DateTime('1970-01-01 00:00:00', new \DateTimeZone('UTC'));
    }

    public function __invoke(): ?string
    {
        if ($this->updated && strpos($this->input, self::UPDATED_PLACEHOLDER) !== false) {
            return strtr($this->input, [self::UPDATED_PLACEHOLDER => $this->updated->format('Y-m-d H:i:s')]);
        }

        return $this->input;
    }
}
