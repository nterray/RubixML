<span style="float:right;"><a href="https://github.com/RubixML/RubixML/blob/master/src/CrossValidation/LeavePOut.php">Source</a></span>

# Leave P Out
Leave P Out tests a learner with a unique holdout set of size p for each round until all samples have been tested.

> **Note:** Leave P Out can take long especially with large datasets and small values of p.

**Interfaces:** [Validator](api.md#validator), [Parallel](#parallel)

### Parameters
| # | Param | Default | Type | Description |
|---|---|---|---|---|
| 1 | p | 10 | int | The number of samples to leave out each round for testing. |

### Example
```php
use Rubix\ML\CrossValidation\LeavePOut;

$validator = new LeavePOut(50);
```