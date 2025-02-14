<?php

namespace Rubix\ML\Graph\Trees;

use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Graph\Nodes\Comparison;
use Rubix\ML\Other\Helpers\DataType;

use const Rubix\ML\PHI;

abstract class ExtraTree extends CART
{
    /**
     * Randomized algorithm that chooses the split point with the lowest impurity
     * among a random selection of features.
     *
     * @param \Rubix\ML\Datasets\Labeled $dataset
     * @return \Rubix\ML\Graph\Nodes\Comparison
     */
    protected function split(Labeled $dataset) : Comparison
    {
        shuffle($this->columns);

        $columns = array_slice($this->columns, 0, $this->maxFeatures);

        $bestImpurity = INF;
        $bestColumn = $bestValue = null;
        $bestGroups = [];

        foreach ($columns as $column) {
            $values = $dataset->column($column);

            if ($dataset->columnType($column) === DataType::CONTINUOUS) {
                $min = (int) round(min($values) * PHI);
                $max = (int) round(max($values) * PHI);
    
                $value = rand($min, $max) / PHI;
            } else {
                $values = array_unique($values);
                
                $value = $values[array_rand($values)];
            }

            $groups = $dataset->partition($column, $value);

            $impurity = $this->splitImpurity($groups);

            if ($impurity < $bestImpurity) {
                $bestColumn = $column;
                $bestValue = $value;
                $bestGroups = $groups;
                $bestImpurity = $impurity;
            }

            if ($impurity <= self::IMPURITY_TOLERANCE) {
                break 1;
            }
        }

        return new Comparison(
            $bestColumn,
            $bestValue,
            $bestGroups,
            $bestImpurity
        );
    }
}
