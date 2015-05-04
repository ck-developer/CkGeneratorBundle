<?php

namespace Ck\Bundle\GeneratorBundle\Command;

use Sensio\Bundle\GeneratorBundle\Command\Validators as SensioValidators;

/**
 * Validator functions.
 *
 * @author Claude Khedhiri <claude@khedhiri.com>
 */
class Validators extends SensioValidators
{

    public static function validateOffice($office)
    {
        $office = strtolower($office);

        if (!in_array($office, array('backend', 'frontend', 'default'))) {
            throw new \RuntimeException(sprintf('Office "%s" is not supported.', $office));
        }

        return $office;
    }
}