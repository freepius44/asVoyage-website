<?php

namespace App\Model\Factory;

use Symfony\Component\Validator\Constraints as Assert,
    Symfony\Component\Validator\ValidatorInterface,
    App\Util\StringUtil;


class Media extends EntityFactory
{
    /**
     * @{inheritdoc}
     */
    public function instantiate() { return []; } // not used

    /**
     * @{inheritdoc}
     */
    protected function processInputData(array $data)
    {
        return [
            'caption'      => StringUtil::cleanText($data['caption'], true),
            'creationDate' => trim($data['creationDate']),
            'geoCoords'    => trim($data['geoCoords']),
            'tags'         => StringUtil::normalizeTags($data['tags']),
        ];
    }

    /**
     * @{inheritdoc}
     */
    protected function getConstraints(array $entity)
    {
        return new Assert\Collection([
            'caption'      => new Assert\Length(['max' => 100]),
            'creationDate' => new Assert\DateTime(),
            'geoCoords'    => new Assert\Regex(['pattern' => '/^\d+(\.\d+)?\s*,\s*\d+(\.\d+)?$/']),
            'tags'         => null,
        ]);
    }
}