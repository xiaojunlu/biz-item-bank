<?php

namespace Codeages\Biz\ItemBank\Assessment\Util;

use Codeages\Biz\Framework\Context\Biz;
use Codeages\Biz\Framework\Util\ArrayToolkit;
use Codeages\Biz\ItemBank\ErrorCode;
use Codeages\Biz\ItemBank\Item\Exception\ItemException;
use Codeages\Biz\ItemBank\Item\Service\ItemService;

class ItemDraw
{
    protected $biz;

    protected $itemRange = [];

    public function __construct(Biz $biz)
    {
        $this->biz = $biz;
    }

    public function drawItems($range, $sections)
    {
        $this->setItemRange($range);

        return $this->findSections($sections);
    }

    protected function findSections($sections)
    {
        foreach ($sections as &$section) {
            $section['items'] = $this->findSectionItems($section['conditions'], $section['item_count']);
        }

        return $sections;
    }

    protected function findSectionItems($conditions, $count)
    {
        $sectionItemRange = [];
        $itemRange = ArrayToolkit::group($this->itemRange, 'type');
        foreach ($conditions['item_types'] as $type) {
            if (empty($itemRange[$type])) {
                continue;
            }
            $sectionItemRange = array_merge($sectionItemRange, $itemRange[$type]);
        }

        if (count($sectionItemRange) < $count) {
            throw new ItemException('item not enough', ErrorCode::ITEM_NOT_ENOUGH);
        }

        if (!empty($conditions['distribution'])) {
            return $this->selectItemsByDifficulty($sectionItemRange, $conditions['distribution'], $count);
        } else {
            return $this->randomSelectItems($sectionItemRange, $count);
        }
    }

    protected function setItemRange($range)
    {
        $conditions = $this->prepareConditions($range);
        $itemCount = $this->getItemService()->countItems($conditions);
        $items = $this->getItemService()->searchItems($conditions, ['created_time' => 'DESC'], 0, $itemCount);

        $this->itemRange = $this->getItemService()->findItemsByIds(ArrayToolkit::column($items, 'id'), true);
    }

    protected function prepareConditions($range)
    {
        $conditions = [
            'bank_id' => $range['bank_id'],
        ];

        if (!empty($range['category_ids'])) {
            $conditions['category_ids'] = $range['category_ids'];
        }

        if (!empty($range['difficulty'])) {
            $conditions['difficulty'] = $range['difficulty'];
        }

        return $conditions;
    }

    protected function selectItemsByDifficulty($items, $distribution, $needCount)
    {
        $selectItems = [];
        $difficultyGroupItems = ArrayToolkit::group($items, 'difficulty');
        foreach ($distribution as $difficulty => $percentage) {
            $subNeedCount = intval($needCount * $percentage / 100);
            if (0 == $subNeedCount) {
                continue;
            }

            if (!empty($difficultyGroupItems[$difficulty])) {
                $sliceItems = $this->randomSelectItems($difficultyGroupItems[$difficulty], $subNeedCount);
                $selectItems = array_merge($selectItems, $sliceItems);
            }
        }
        $selectItems = $this->fillItemsToNeedCount($selectItems, $items, $needCount);

        return $selectItems;
    }

    protected function fillItemsToNeedCount($selectedItems, $allItems, $needCount)
    {
        $indexedItems = ArrayToolkit::index($allItems, 'id');
        foreach ($selectedItems as $item) {
            unset($indexedItems[$item['id']]);
        }

        if (count($selectedItems) < $needCount) {
            $stillNeedCount = $needCount - count($selectedItems);
        } else {
            $stillNeedCount = 0;
        }

        if ($stillNeedCount) {
            $items = array_slice(array_values($indexedItems), 0, $stillNeedCount);
            $selectedItems = array_merge($selectedItems, $items);
        }

        return $selectedItems;
    }

    protected function randomSelectItems($items, $needCount)
    {
        if (count($items) < $needCount) {
            $needCount = count($items);
        }

        if (0 == $needCount) {
            return [];
        }

        $randKeys = array_rand($items, $needCount);
        $randKeys = is_array($randKeys) ? $randKeys : array($randKeys);
        $selectItems = [];
        foreach ($randKeys as $key) {
            $selectItems[] = $items[$key];
        }

        return $selectItems;
    }

    /**
     * @return ItemService
     */
    protected function getItemService()
    {
        return $this->biz->service('ItemBank:Item:ItemService');
    }
}
