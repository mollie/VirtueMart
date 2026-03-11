<?php

namespace Mollie\Payment\Repository;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Mollie\BusinessLogic\ORM\Interfaces\RepositoryInterface;
use Mollie\Infrastructure\ORM\Entity;
use Mollie\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use Mollie\Infrastructure\ORM\QueryFilter\QueryFilter;
use Mollie\Infrastructure\ORM\QueryFilter\QueryCondition;
use Mollie\Infrastructure\ORM\QueryFilter\Operators;
use Mollie\Infrastructure\ORM\Utility\IndexHelper;

class BaseRepository implements RepositoryInterface
{
    const THIS_CLASS_NAME = __CLASS__;
    const TABLE_NAME = '#__mollie_entities';

    protected $entityClass;

    /**
     * @inheritDoc
     */
    public static function getClassName(): string
    {
        return static::THIS_CLASS_NAME;
    }

    /**
     * @inheritDoc
     */
    public function setEntityClass($entityClass): void
    {
        $this->entityClass = $entityClass;
    }

    /**
     * @inheritDoc
     */
    public function select(QueryFilter $filter = null): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $this->buildSelectQuery($filter);

        $db->setQuery($query);
        $results = $db->loadObjectList();

        return $this->unserializeResults($results);
    }

    /**
     * @inheritDoc
     */
    public function selectOne(QueryFilter $filter = null): Entity|null
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $this->buildSelectQuery($filter);
        $query->setLimit(1);

        $db->setQuery($query);
        $result = $db->loadObject();

        if (!$result) {
            return null;
        }

        $entities = $this->unserializeResults([$result]);

        return !empty($entities[0]) ? $entities[0] : null;
    }

    /**
     * @inheritDoc
     */
    public function save(Entity $entity): int
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        /** @var Entity $entity */
        $type = $entity->getConfig()->getType();
        $indexMap = IndexHelper::transformFieldsToIndexes($entity);

        $data = new \stdClass();
        $data->type = $type;
        $data->data = json_encode($entity->toArray());

        foreach ($indexMap as $index => $value) {
            $indexField = 'index' . $index;
            $data->{$indexField} = $value;
        }

        $db->insertObject(self::TABLE_NAME, $data);
        $id = $db->insertid();
        $entity->setId($id);

        return $id;
    }

    /**
     * @inheritDoc
     */
    public function update(Entity $entity): bool
    {
        if (!$entity->getId()) {
            return false;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $type = $entity->getConfig()->getType();
        $indexMap = IndexHelper::transformFieldsToIndexes($entity);

        $data = new \stdClass();
        $data->id = $entity->getId();
        $data->type = $type;
        $data->data = json_encode($entity->toArray());

        foreach ($indexMap as $index => $value) {
            $indexField = 'index' . $index;
            $data->{$indexField} = $value;
        }

        return $db->updateObject(self::TABLE_NAME, $data, 'id');
    }

    /**
     * @inheritDoc
     */
    public function delete(Entity $entity): bool
    {
        if (!$entity->getId()) {
            return false;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery();

        $query->delete($db->quoteName(self::TABLE_NAME))
            ->where($db->quoteName('id') . ' = ' . (int)$entity->getId());

        $db->setQuery($query);

        try {
            $db->execute();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteBy(QueryFilter $filter = null): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery();

        /** @var Entity $entity */
        $entity = new $this->entityClass;
        $type = $entity->getConfig()->getType();

        $query->delete($db->quoteName(self::TABLE_NAME))
            ->where($db->quoteName('type') . ' = ' . $db->quote($type));

        if ($filter) {
            $this->applyFilters($query, $filter, $entity);
        }

        $db->setQuery($query);

        try {
            $db->execute();
        } catch (\Exception $e) {
            // Silent fail
        }
    }

    /**
     * @inheritDoc
     */
    public function saveOrUpdate(Entity $entity): int
    {
        if ($entity->getId()) {
            $this->update($entity);

            return $entity->getId();
        }

        return $this->save($entity);
    }

    /**
     * @inheritDoc
     */
    public function count(QueryFilter $filter = null): int
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $this->buildSelectQuery($filter, true);

        $db->setQuery($query);

        return (int)$db->loadResult();
    }

    /**
     * @param QueryFilter|null $filter
     * @param bool $isCount
     *
     * @return \Joomla\Database\QueryInterface
     * @throws QueryFilterInvalidParamException
     */
    protected function buildSelectQuery(QueryFilter $filter = null, bool $isCount = false)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery();

        /** @var Entity $entity */
        $entity = new $this->entityClass;
        $type = $entity->getConfig()->getType();

        if ($isCount) {
            $query->select('COUNT(*)');
        } else {
            $query->select('*');
        }

        $query->from($db->quoteName(self::TABLE_NAME))
            ->where($db->quoteName('type') . ' = ' . $db->quote($type));

        if ($filter) {
            $this->applyFilters($query, $filter, $entity);

            if (!$isCount) {
                $this->applyOrderBy($query, $filter, $entity);
                $this->applyLimitOffset($query, $filter);
            }
        }

        return $query;
    }

    /**
     * @param \Joomla\Database\QueryInterface $query
     * @param QueryFilter $filter
     * @param Entity $entity
     *
     * @return void
     *
     * @throws QueryFilterInvalidParamException
     */
    protected function applyFilters($query, QueryFilter $filter, Entity $entity): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $indexMap = IndexHelper::mapFieldsToIndexes($entity);
        $indexMap['id'] = 0;

        $conditions = $filter->getConditions();
        if (empty($conditions)) {
            return;
        }

        $groups = $this->buildConditionGroups($filter, $indexMap);

        foreach ($groups as $groupIndex => $group) {
            $subConditions = [];

            foreach ($group as $condition) {
                $subConditions[] = $this->buildCondition($condition, $indexMap, $db);
            }

            if (!empty($subConditions)) {
                if ($groupIndex === 0) {
                    $query->where('(' . implode(' AND ', $subConditions) . ')');
                } else {
                    $query->orWhere('(' . implode(' AND ', $subConditions) . ')');
                }
            }
        }
    }

    /**
     * @param QueryFilter $filter
     * @param array $fieldIndexMap
     *
     * @return array
     *
     * @throws QueryFilterInvalidParamException
     */
    protected function buildConditionGroups(QueryFilter $filter, array $fieldIndexMap): array
    {
        $groups = [];
        $counter = 0;

        foreach ($filter->getConditions() as $condition) {
            if (!empty($groups[$counter]) && $condition->getChainOperator() === 'OR') {
                $counter++;
            }

            if (!array_key_exists($condition->getColumn(), $fieldIndexMap)) {
                throw new QueryFilterInvalidParamException("Field [{$condition->getColumn()}] is not indexed.");
            }

            $groups[$counter][] = $condition;
        }

        return $groups;
    }

    /**
     * @param QueryCondition $condition
     * @param array $indexMap
     * @param \Joomla\Database\DatabaseInterface $db
     *
     * @return string
     */
    protected function buildCondition(QueryCondition $condition, array $indexMap, $db): string
    {
        $column = $condition->getColumn();

        if ($column === 'id') {
            $columnName = $db->quoteName('id');
        } else {
            $columnName = $db->quoteName('index' . $indexMap[$column]);
        }

        $operator = $condition->getOperator();
        $value = $condition->getValue();

        if (in_array($operator, [Operators::IN, Operators::NOT_IN], true)) {
            $values = array_map(function($item) use ($db) {
                return $db->quote(IndexHelper::castFieldValue($item, gettype($item)));
            }, $value);

            return $columnName . ' ' . $operator . ' (' . implode(',', $values) . ')';
        }

        if (in_array($operator, [Operators::NULL, Operators::NOT_NULL], true)) {
            return $columnName . ' ' . $operator;
        }

        $quotedValue = $db->quote(IndexHelper::castFieldValue($value, $condition->getValueType()));
        return $columnName . ' ' . $operator . ' ' . $quotedValue;
    }

    /**
     * @param \Joomla\Database\QueryInterface $query
     * @param QueryFilter $filter
     * @param Entity $entity
     *
     * @return void
     */
    protected function applyOrderBy($query, QueryFilter $filter, Entity $entity): void
    {
        if (!$filter->getOrderByColumn()) {
            return;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $orderByColumn = $filter->getOrderByColumn();
        $indexMap = IndexHelper::mapFieldsToIndexes($entity);

        if ($orderByColumn === 'id') {
            $columnName = $db->quoteName('id');
        } elseif (isset($indexMap[$orderByColumn])) {
            $columnName = $db->quoteName('index' . $indexMap[$orderByColumn]);
        } else {
            return;
        }

        $query->order($columnName . ' ' . $filter->getOrderDirection());
    }

    /**
     * @param \Joomla\Database\QueryInterface $query
     * @param QueryFilter $filter
     *
     * @return void
     */
    protected function applyLimitOffset($query, QueryFilter $filter): void
    {
        if ($filter->getLimit()) {
            $query->setLimit($filter->getLimit(), $filter->getOffset() ?: 0);
        } elseif ($filter->getOffset()) {
            $query->setLimit(0, $filter->getOffset());
        }
    }

    /**
     * @param array $results
     *
     * @return array
     */
    protected function unserializeResults(array $results): array
    {
        $entities = [];

        foreach ($results as $row) {
            $entity = $this->unserializeEntity($row->data);
            if ($entity) {
                $entity->setId($row->id);
                $entities[] = $entity;
            }
        }

        return $entities;
    }

    /**
     * @param $data
     *
     * @return Entity
     */
    protected function unserializeEntity($data): Entity
    {
        $jsonEntity = json_decode($data, true);

        if (isset($jsonEntity['class_name'])) {
            $entity = new $jsonEntity['class_name'];
        } else {
            $entity = new $this->entityClass;
        }

        /** @var Entity $entity */
        $entity->inflate($jsonEntity);

        return $entity;
    }
}
