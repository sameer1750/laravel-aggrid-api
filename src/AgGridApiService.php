<?php

namespace Radix\Aggrid;

class AgGridApiService {

    public function buildSql($tableName, $request, $limit = true) {

        $selectSql = $this->createSelectSql($request);
        $fromSql = "FROM $tableName ";
        $whereSql = $this->createWhereSql($request);
        $limitSql = $this->createLimitSql($request);

        $orderBySql = $this->createOrderBySql($request);
        $groupBySql = $this->createGroupBySql($request);

        $sql = $selectSql . $fromSql . $whereSql . $groupBySql . $orderBySql ;

        if ($limit) {
           $sql  .= $limitSql;
        }
        return $sql;
    }

    public function createSelectSql($request) {
        $rowGroupCols = $request->rowGroupCols;
        $valueCols = $request->valueCols;
        $groupKeys = $request->groupKeys;

        if (count($valueCols)) {
            $colsToSelect = [];

            foreach($valueCols as $v) {
                $colsToSelect[] = $v['field'];
            }

            return ' select ' . join(', ', $colsToSelect). ' ';
        }

        return ' select *';
    }

    public function createWhereSql($request) {
        $rowGroupCols = $request->rowGroupCols;
        $groupKeys = $request->groupKeys;
        $filterModel = $request->filterModel;

        $whereParts = [];

        foreach($groupKeys as $key => $value) {
            $colName = $rowGroupCols[$index]->field;
            $whereParts[] = $colName . ' = "' . $key . '"';
        }

        foreach ($filterModel as $key => $value) {
            $item = $filterModel[$key];
            $whereParts[] = $this->createFilterSql($key, $value);
        }

        if (count($whereParts) > 0) {
            return ' where ' . join(" and ", $whereParts);
        } else {
            return '';
        }
    }

    public function createFilterSql($key, $item) {
        switch ($item['filterType']) {
            case 'text':
                return $this->createTextFilterSql($key, $item);
            case 'number':
                return $this->createNumberFilterSql($key, $item);
            case 'date':
                return $this->createDateFilterSql($key, $item);
            case 'set':
                return $this->createSetFilter($key, $item);
            default:
                logger('unkonwn filter type: ' . $item['filterType']);
        }
    }

    private function createSetFilter($key, $item) {
        return $key .' in ('."'" . implode ( "', '", $item['values'] ) . "'".')';
    }

    public function createDateFilterSql($key, $item) {
        switch ($item['type']) {
            case 'equals':
                return $key . ' = "' . $item['dateFrom'] . '"';
            case 'notEqual':
                return $key . ' != "' . $item['dateFrom'] . '"';
            default:
                logger('unknown text filter type: ' . $item['dateFrom']);
                return 'true';
        }
    }

    public function createTextFilterSql($key, $item) {
        switch ($item['type']) {
            case 'equals':
                return $key . ' = "' . $item['filter'] . '"';
            case 'notEqual':
                return $key . ' != "' . $item['filter'] . '"';
            case 'contains':
                return $key . ' like "%' . $item['filter'] . '%"';
            case 'notContains':
                return $key . ' not like "%' . $item['filter'] . '%"';
            case 'startsWith':
                return $key . ' like "' . $item['filter'] . '%"';
            case 'endsWith':
                return $key . ' like "%' . $item['filter'] . '"';
            default:
                logger('unknown text filter type: ' . $item['type']);
                return 'true';
        }
    }

    public function createNumberFilterSql($key, $item) {
        switch (item.type) {
            case 'equals':
                return $key + ' = ' + $item['filter'];
            case 'notEqual':
                return $key + ' != ' + $item['filter'];
            case 'greaterThan':
                return $key + ' > ' + $item['filter'];
            case 'greaterThanOrEqual':
                return $key + ' >= ' + $item['filter'];
            case 'lessThan':
                return $key + ' < ' + $item['filter'];
            case 'lessThanOrEqual':
                return $key + ' <= ' + $item['filter'];
            case 'inRange':
                return '(' + $key + ' >= ' + $item['filter'] + ' and ' + $key + ' <= ' + $item['filterTo'] + ')';
            default:
                logger('unknown number filter type: ' + $item['type']);
                return 'true';
        }
    }

    public function isDoingGrouping($rowGroupCols, $groupKeys) {
        // we are not doing grouping if at the lowest level. we are at the lowest level
        // if we are grouping by more columns than we have keys for (that means the user
        // has not expanded a lowest level group, OR we are not grouping at all).
        return count($rowGroupCols) > count($groupKeys);
    }

    public function createLimitSql($request) {
        $startRow = $request->startRow;
        $endRow = $request->endRow;
        $pageSize = $endRow - $startRow;
        return ' limit ' . ($pageSize + 1) . ' offset ' . $startRow;
    }

    public function createOrderBySql($request) {
        $rowGroupCols = $request->rowGroupCols;
        $groupKeys = $request->groupKeys;
        $sortModel = $request->sortModel;

        $grouping = $this->isDoingGrouping($rowGroupCols, $groupKeys);

        $sortParts = [];
        if ($sortModel) {
            foreach($sortModel as $key=>$item) {
                $sortParts[] = $item['colId'] . ' ' . $item['sort'];
            }
        }
        
        if (count($sortParts) > 0) {
            return ' order by ' . join(', ', $sortParts);
        } else {
            return '';
        }
    }

    public function createGroupBySql($request) {
        $rowGroupCols = $request->rowGroupCols;
        $groupKeys = $request->groupKeys;

        if ($this->isDoingGrouping($rowGroupCols, $groupKeys)) {
            $colsToGroupBy = [];

            $rowGroupCol = $rowGroupCols[count($groupKeys)];
            $colsToGroupBy[] = $rowGroupCol['field'];

            return ' group by ' + join(', ',$colsToGroupBy);
        } else {
            // select all columns
            return '';
        }
    }
}