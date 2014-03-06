<?php
    /**
     * Phpro ZF2 Library
     *
     * @link      http://fisheye.phpro.be/git/Git-Vlir-Uos.git
     * @copyright Copyright (c) 2012 PHPro
     * @license   http://opensource.org/licenses/gpl-license.php GNU Public License
     *
     */

namespace Phpro\DoctrineHydrationModule\Hydrator\ODM\MongoDB;

use DoctrineModule\Persistence\ObjectManagerAwareInterface;
use DoctrineModule\Stdlib\Hydrator\DoctrineObject as BaseHydrator;
use DoctrineModule\Stdlib\Hydrator\Strategy as DoctrineStrategy;
use Phpro\DoctrineHydrationModule\Hydrator\ODM\MongoDB\Strategy\AbstractMongoStrategy;

/**
 * Class DoctrineObject
 *
 * @package Phpro\DoctrineHydrationModule\Hydrator\Strategy\ODM
 */
class DoctrineObject extends BaseHydrator
{

    /**
     * TODO: For the moment only byValue configured...
     *
     * @throws InvalidArgumentException
     */
    protected function prepareStrategies()
    {
        $associations = $this->metadata->getAssociationNames();
        foreach ($associations as $association) {

            // Add meta data to existing collections:
            if ($this->hasStrategy($association)) {
                $strategy = $this->getStrategy($association);
                $this->injectStrategyDependencies($strategy, $association);
                continue;
            }

            // Create new strategy based on type of filed
            $fieldMeta = $this->metadata->fieldMappings[$association];
            $reference = isset($fieldMeta['reference']) && $fieldMeta['reference'];
            $embedded = isset($fieldMeta['embedded']) && $fieldMeta['embedded'];
            $isCollection = $this->metadata->isCollectionValuedAssociation($association);
            $strategy = null;

            if ($isCollection) {
                if ($reference) {
                    $strategy = new Strategy\ReferencedCollection($this->objectManager);
                } elseif ($embedded) {
                    $strategy = new Strategy\EmbeddedCollection($this->objectManager);
                }
            } else {
                if ($reference) {
                    $strategy = new Strategy\ReferencedField($this->objectManager);
                } elseif ($embedded) {
                    $strategy = new Strategy\EmbeddedField($this->objectManager);
                }
            }

            // Add meta data
            if ($strategy) {
                $this->injectStrategyDependencies($strategy, $association);
                $this->addStrategy($association, $strategy);
            }
        }

        // Call through for DI
        parent::prepareStrategies();
    }

    /**
     * Inject dependencies to strategy that is injected in a later state
     *
     * @param $strategy
     * @param $association
     */
    protected function injectStrategyDependencies($strategy, $association)
    {

        if ($strategy instanceof DoctrineStrategy\AbstractCollectionStrategy) {
            $strategy->setCollectionName($association);
            $strategy->setClassMetadata($this->metadata);
        }

        if ($strategy instanceof ObjectManagerAwareInterface) {
            $strategy->setObjectManager($this->objectManager);
        }
    }

    /**
     * Make sure to only use the mongoDB ODM strategies for onMany
     *
     * @param object $object
     * @param mixed  $collectionName
     * @param string $target
     * @param mixed  $values
     */
    protected function toMany($object, $collectionName, $target, $values)
    {
        if ($this->hasStrategy($collectionName)) {
            $strategy = $this->getStrategy($collectionName);

            if ($strategy instanceof AbstractMongoStrategy) {
                $strategy->setObject($object);
                $this->hydrateValue($collectionName, $values, $values);
                return;
            }

        }

        parent::toMany($object, $collectionName, $target, $values);
    }

}
