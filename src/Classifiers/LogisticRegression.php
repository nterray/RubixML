<?php

namespace Rubix\ML\Classifiers;

use Rubix\ML\Online;
use Rubix\ML\Verbose;
use Rubix\ML\Estimator;
use Rubix\Tensor\Matrix;
use Rubix\ML\Persistable;
use Rubix\ML\Probabilistic;
use Rubix\ML\Datasets\Dataset;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Other\Helpers\Params;
use Rubix\ML\NeuralNet\FeedForward;
use Rubix\ML\Other\Helpers\DataType;
use Rubix\ML\NeuralNet\Layers\Binary;
use Rubix\ML\Other\Traits\LoggerAware;
use Rubix\ML\NeuralNet\Optimizers\Adam;
use Rubix\ML\Other\Traits\PredictsSingle;
use Rubix\ML\NeuralNet\Layers\Placeholder1D;
use Rubix\ML\NeuralNet\Optimizers\Optimizer;
use Rubix\ML\NeuralNet\CostFunctions\CrossEntropy;
use Rubix\ML\NeuralNet\CostFunctions\ClassificationLoss;
use Rubix\ML\Other\Specifications\DatasetIsCompatibleWithEstimator;
use InvalidArgumentException;
use RuntimeException;

use const Rubix\ML\EPSILON;

/**
 * Logistic Regression
 *
 * A type of linear classifier that uses the logistic (*sigmoid*) function to estimate
 * the probabilities of exactly *two* classes. The model parameters (weights and bias)
 * are solved using mini batch Gradient Descent with pluggable optimizers and cost
 * functions that run on the neural network subsystem.
 *
 * @category    Machine Learning
 * @package     Rubix/ML
 * @author      Andrew DalPino
 */
class LogisticRegression implements Estimator, Online, Probabilistic, Verbose, Persistable
{
    use PredictsSingle, LoggerAware;

    /**
     * The number of training samples to consider per iteration of gradient descent.
     *
     * @var int
     */
    protected $batchSize;

    /**
     * The gradient descent optimizer.
     *
     * @var \Rubix\ML\NeuralNet\Optimizers\Optimizer
     */
    protected $optimizer;

    /**
     * The L2 regularization parameter.
     *
     * @var float
     */
    protected $alpha;

    /**
     * The maximum number of training epochs. i.e. the number of times to iterate
     * over the entire training set.
     *
     * @var int
     */
    protected $epochs;

    /**
     * The minimum change in the cost function necessary to continue training.
     *
     * @var float
     */
    protected $minChange;

    /**
     * The function that computes the cost of an erroneous activation during
     * training.
     *
     * @var \Rubix\ML\NeuralNet\CostFunctions\ClassificationLoss
     */
    protected $costFn;

    /**
     * The number of epochs without improvement in the training loss to wait
     * before considering an early stop.
     *
     * @var int
     */
    protected $window;

    /**
     * The underlying neural network instance.
     *
     * @var \Rubix\ML\NeuralNet\FeedForward|null
     */
    protected $network;

    /**
     * The unique class labels.
     *
     * @var array|null
     */
    protected $classes;

    /**
     * The average cost of a training sample at each epoch.
     *
     * @var array
     */
    protected $steps = [
        //
    ];

    /**
     * @param int $batchSize
     * @param \Rubix\ML\NeuralNet\Optimizers\Optimizer|null $optimizer
     * @param float $alpha
     * @param int $epochs
     * @param float $minChange
     * @param \Rubix\ML\NeuralNet\CostFunctions\ClassificationLoss|null $costFn
     * @param int $window
     * @throws \InvalidArgumentException
     */
    public function __construct(
        int $batchSize = 100,
        ?Optimizer $optimizer = null,
        float $alpha = 1e-4,
        int $epochs = 1000,
        float $minChange = 1e-4,
        ?ClassificationLoss $costFn = null,
        int $window = 5
    ) {
        if ($batchSize < 1) {
            throw new InvalidArgumentException('Cannot have less than 1 sample'
                . " per batch, $batchSize given.");
        }

        if ($alpha < 0.) {
            throw new InvalidArgumentException('L2 regularization amount must'
                . " be 0 or greater, $alpha given.");
        }

        if ($epochs < 1) {
            throw new InvalidArgumentException('Estimator must train for at'
                . " least 1 epoch, $epochs given.");
        }

        if ($minChange < 0.) {
            throw new InvalidArgumentException('Minimum change cannot be less'
                . " than 0, $minChange given.");
        }

        if ($window < 1) {
            throw new InvalidArgumentException('Window must be at least 1'
                . " epoch, $window given.");
        }

        $this->batchSize = $batchSize;
        $this->optimizer = $optimizer ?? new Adam();
        $this->alpha = $alpha;
        $this->epochs = $epochs;
        $this->minChange = $minChange;
        $this->costFn = $costFn ?? new CrossEntropy();
        $this->window = $window;
    }

    /**
     * Return the integer encoded estimator type.
     *
     * @return int
     */
    public function type() : int
    {
        return self::CLASSIFIER;
    }

    /**
     * Return the data types that this estimator is compatible with.
     *
     * @return int[]
     */
    public function compatibility() : array
    {
        return [
            DataType::CONTINUOUS,
        ];
    }

    /**
     * Has the learner been trained?
     *
     * @return bool
     */
    public function trained() : bool
    {
        return $this->network and $this->classes;
    }

    /**
     * Return the average cost at every epoch.
     *
     * @return array
     */
    public function steps() : array
    {
        return $this->steps;
    }

    /**
     * Return the underlying neural network instance or null if not trained.
     *
     * @return \Rubix\ML\NeuralNet\FeedForward|null
     */
    public function network() : ?FeedForward
    {
        return $this->network;
    }

    /**
     * Train the learner with a dataset.
     *
     * @param \Rubix\ML\Datasets\Dataset $dataset
     * @throws \InvalidArgumentException
     */
    public function train(Dataset $dataset) : void
    {
        if (!$dataset instanceof Labeled) {
            throw new InvalidArgumentException('This estimator requires a'
                . ' labeled training set.');
        }

        $this->classes = $dataset->possibleOutcomes();

        $this->network = new FeedForward(
            new Placeholder1D($dataset->numColumns()),
            [],
            new Binary($this->classes, $this->alpha, $this->costFn),
            $this->optimizer
        );

        $this->steps = [];

        $this->partial($dataset);
    }

    /**
     * Perform a partial train on the learner.
     *
     * @param \Rubix\ML\Datasets\Dataset $dataset
     * @throws \InvalidArgumentException
     */
    public function partial(Dataset $dataset) : void
    {
        if (!$this->network) {
            $this->train($dataset);

            return;
        }

        if (!$dataset instanceof Labeled) {
            throw new InvalidArgumentException('This estimator requires a'
                . ' labeled training set.');
        }

        DatasetIsCompatibleWithEstimator::check($dataset, $this);

        if ($this->logger) {
            $this->logger->info('Learner init ' . Params::stringify([
                'batch_size' => $this->batchSize,
                'optimizer' => $this->optimizer,
                'alpha' => $this->alpha,
                'epochs' => $this->epochs,
                'min_change' => $this->minChange,
                'cost_fn' => $this->costFn,
            ]));
        }

        $prevLoss = $bestLoss = INF;
        $nu = 0;

        for ($epoch = 1; $epoch <= $this->epochs; $epoch++) {
            $batches = $dataset->randomize()->batch($this->batchSize);

            $loss = 0.;

            foreach ($batches as $batch) {
                $loss += $this->network->roundtrip($batch);
            }

            $loss /= count($batches);

            $this->steps[] = $loss;

            if ($this->logger) {
                $this->logger->info("Epoch $epoch loss=$loss");
            }

            if ($loss < $bestLoss) {
                $bestLoss = $loss;
                
                $nu = 0;
            } else {
                $nu++;
            }

            if (is_nan($loss) or $loss < EPSILON) {
                break 1;
            }

            if (abs($prevLoss - $loss) < $this->minChange) {
                break 1;
            }

            if ($nu >= $this->window) {
                break 1;
            }

            $prevLoss = $loss;
        }

        if ($this->logger) {
            $this->logger->info('Training complete');
        }
    }

    /**
     * Make predictions from a dataset.
     *
     * @param \Rubix\ML\Datasets\Dataset $dataset
     * @return array
     */
    public function predict(Dataset $dataset) : array
    {
        return array_map('Rubix\ML\argmax', $this->proba($dataset));
    }

    /**
     * Estimate probabilities for each possible outcome.
     *
     * @param \Rubix\ML\Datasets\Dataset $dataset
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @return array
     */
    public function proba(Dataset $dataset) : array
    {
        if (!$this->network or !$this->classes) {
            throw new RuntimeException('The estimator has not'
                . ' been trained.');
        }

        DatasetIsCompatibleWithEstimator::check($dataset, $this);

        $xT = Matrix::quick($dataset->samples())->transpose();

        $y = $this->network->infer($xT)->row(0);

        [$classA, $classB] = $this->classes;

        $probabilities = [];

        foreach ($y as $activation) {
            $probabilities[] = [
                $classA => 1. - $activation,
                $classB => $activation,
            ];
        }

        return $probabilities;
    }
}
