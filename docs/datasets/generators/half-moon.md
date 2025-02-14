<span style="float:right;"><a href="https://github.com/RubixML/RubixML/blob/master/src/Datasets/Generators/HalfMoon.php">Source</a></span>

# Half Moon
Generate a dataset consisting of 2 dimensional samples that form a half moon shape when plotted on a chart. The label for each sample is the value obtained by reversing the generative process for that particular sample.

**Data Types:** Continuous

**Label Type:** Continuous

### Parameters
| # | Param | Default | Type | Description |
|---|---|---|---|---|
| 1 | x | 0.0 | float | The *x* coordinate of the center of the half moon. |
| 2 | y | 0.0 | float | The *y* coordinate of the center of the half moon. |
| 3 | scale | 1.0 | float | The scaling factor of the half moon. |
| 4 | rotate | 90.0 | float | The amount in degrees to rotate the half moon counterclockwise. |
| 5 | noise | 0.1 | float | The amount of Gaussian noise to add to each data point as a percentage of the scaling factor. |

### Additional Methods
This generator does not have any additional methods.

### Example
```php
use Rubix\ML\Datasets\Generators\HalfMoon;

$generator = new HalfMoon(4.0, 0.0, 6, 180.0, 0.2);
```