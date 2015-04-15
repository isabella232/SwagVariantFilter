<?php
namespace Shopware\SwagVariantFilter\Components\Common;

use Doctrine\DBAL\Driver\PDOStatement;
use Doctrine\DBAL\Portability\Connection;

/**
 * Class DatabaseAdapter
 *
 * Handling DB-Tasks to generate options and informtaion
 *
 * @package Shopware\SwagVariantFilter\Components\LegacyFilter
 */
class DatabaseAdapter
{

    /**
     * Small cache data between different calls
     *
     * @var array
     */
    protected $subCategories = array();

    private function getConfigurationOptionQueryBuilder()
    {
        return Shopware()->Models()->getDBALQueryBuilder()
            ->select('gr.name AS group_name, gr.id AS group_id, opt.name AS option_name, opt.id AS option_id')
            ->from('s_article_configurator_options', 'opt')
            ->innerJoin(
                'opt',
                's_article_configurator_groups',
                'gr',
                'opt.group_id = gr.id')
            ->innerJoin(
                'gr',
                's_article_configurator_option_relations',
                'rel',
                'rel.option_id = opt.id'
            )
            ->groupBy('opt.id, opt.name, gr.id, gr.name ')
            ->orderBy('gr.id, opt.position');
    }

    /**
     * @todo there was a optional subselect, but I could not detrmine any use for this, so it's gone now!
     *
     * @param array $subCategories
     * @return mixed
     */
    public function getConfigurationOptionsFromCategoryIds(array $subCategories)
    {
        $builder = $this->getConfigurationOptionQueryBuilder()
            ->innerJoin(
                'rel',
                's_articles_details',
                'det',
                'det.id = rel.article_id'
            )
            ->innerJoin(
                'det',
                's_articles_categories',
                'cat',
                'cat.articleID = det.articleID'
            )
            ->where('cat.categoryID IN (:subcategoryIds)')
            ->setParameter(
                ':subcategoryIds',
                $subCategories, Connection::PARAM_INT_ARRAY
            );

//
//        if ($optionIds != '') {
//            $additionSQL = "AND s_articles_categories.articleID IN (
//                SELECT DISTINCT
//                  s_articles_details.articleID
//                  FROM
//                  s_articles_details
//                  JOIN s_article_configurator_option_relations
//                    ON s_article_configurator_option_relations.article_id = s_articles_details.id
//                    AND s_article_configurator_option_relations.option_id IN ($optionIds)
//                  JOIN s_articles_categories
//                    ON s_articles_details.articleID=s_articles_categories.articleID
//                    AND s_articles_categories.categoryID IN ($subCategoriesTxt)
//                )";
//        }


        return $builder->execute()
            ->fetchAll();
    }

    /**
     * Generates raw data
     *
     * @param array $groupIds
     * @return mixed
     */
    public function getConfigurationOptionsFromGroupIds(array $groupIds)
    {
        $builder = $this->getConfigurationOptionQueryBuilder()
            ->where('gr.id IN (:groupIds)')
            ->setParameter(':groupIds', $groupIds, Connection::PARAM_INT_ARRAY);

        return $builder->execute()
            ->fetchAll();
    }

    /**
     * @param $parentCatId
     * @return string
     */
    public function getSubcategoriesWhereIn($parentCatId)
    {
        $categories = $this->getSubcategories($parentCatId);

        return implode(',', $categories);
    }

    /**
     * Returns a flat array with the subcategories and the parent itself
     *
     * The result is not sorted!
     *
     * @param $parentCatId
     * @return array
     */
    public function getSubcategories($parentCatId)
    {
        if (!$parentCatId) {
            throw new \InvalidArgumentException('Missing required param $parentCatId');
        }

        if (!isset($this->subCategories[$parentCatId])) {
            /** @var PDOStatement $stmt */
            $stmt = Shopware()->Models()->getDBALQueryBuilder()
                ->select('cat.id')
                ->from('s_categories', 'cat')
                ->where('id = :parentId OR cat.path LIKE "%:parentIdPath%"')
                ->setParameters(array(
                    'parentId' => $parentCatId,
                    'parentIdPath' => '|' . $parentCatId . '|'
                ))
                ->execute();

            $stmt->setFetchMode(\PDO::FETCH_COLUMN, 0);
            $this->subCategories[$parentCatId] = $stmt->fetchAll();
        }

        return $this->subCategories[$parentCatId];
    }
}