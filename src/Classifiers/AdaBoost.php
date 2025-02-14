<?php

namespace Rubix\ML\Classifiers;

use Rubix\ML\Learner;
use Rubix\ML\Verbose;
use Rubix\ML\Estimator;
use Rubix\ML\Persistable;
use Rubix\ML\Probabilistic;
use Rubix\ML\Datasets\Dataset;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Other\Helpers\Params;
use Rubix\ML\Other\Traits\LoggerAware;
use Rubix\ML\Other\Traits\PredictsSingle;
use Rubix\ML\Other\Specifications\DatasetIsCompatibleWithEstimator;
use InvalidArgumentException;
use RuntimeException;

use const Rubix\ML\EPSILON;

/**
 * AdaBoost
 *
 * Short for *Adaptive Boosting*, this ensemble classifier can improve the
 * performance of an otherwise *weak* classifier by focusing more attention on
 * samples that are harder to classify. It builds an additive model where at
 * each stage a new learner is instantiated and trained.
 *
 * > **Note**: The default base classifier is a *Decision Stump* i.e a
 * Classification Tree with a max depth of 1.
 *
 * References:
 * [1] Y. Freund et al. (1996). A Decision-theoretic Generalization of On-line
 * Learning and an Application to Boosting.
 * [2] J. Zhu et al. (2006). Multi-class AdaBoost.
 *
 * @category    Machine Learning
 * @package     Rubix/ML
 * @author      Andrew DalPino
 */
class AdaBoost implements Estimator, Learner, Probabilistic, Verbose, Persistable
{
    use PredictsSingle, LoggerAware;
    
    /**
     * The base classifier to be boosted.
     *
     * @var \Rubix\ML\Learner
     */
    protected $base;

    /**
     * The number of estimators to train in the ensemble.
     *
     * @var int
     */
    protected $estimators;

    /**
     * The learning rate i.e the step size.
     *
     * @var float
     */
    protected $rate;

    /**
     * The ratio of samples to train each weak learner on.
     *
     * @var float
     */
    protected $ratio;
    
    /**
     * The class labels of the training set.
     *
     * @var array
     */
    protected $classes = [
        //
    ];

    /**
     * The ensemble of "weak" classifiers.
     *
     * @var array
     */
    protected $ensemble = [
        //
    ];

    /**
     * The weight of each training sample in the dataset.
     *
     * @var array
     */
    protected $weights = [
        //
    ];

    /**
     * The amount of influence a particular classifier has. i.e. the
     * classifier's ability to make accurate predictions.
     *
     * @var array
     */
    protected $influences = [
        //
    ];

    /**
     * The average error of a training sample at each epoch.
     *
     * @var array
     */
    protected $steps = [
        //
    ];

    /**
     * @param \Rubix\ML\Learner|null $base
     * @param int $estimators
     * @param float $rate
     * @param float $ratio
     * @throws \InvalidArgumentException
     */
    public function __construct(
        ?Learner $base = null,
        int $estimators = 100,
        float $rate = 1.,
        float $ratio = 0.8
    ) {
        if ($base and $base->type() !== self::CLASSIFIER) {
            throw new InvalidArgumentException('Base estimator must be a'
                . ' classifier, ' . self::TYPES[$base->type()] . ' given.');
        }

        if ($estimators < 1) {
            throw new InvalidArgumentException('Ensemble must contain at least'
                . " 1 estimator, $estimators given.");
        }

        if ($rate < 0.) {
            throw new InvalidArgumentException('Learning rate must be greater'
                . " than 0, $rate given.");
        }

        if ($ratio <= 0 or $ratio > 1.) {
            throw new InvalidArgumentException('Ratio must be between'
                . " 0 and 1, $ratio given.");
        }

        $this->base = $base ?? new ClassificationTree(1);
        $this->estimators = $estimators;
        $this->rate = $rate;
        $this->ratio = $ratio;
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
        return $this->base->compatibility();
    }

    /**
     * Has the learner been trained?
     *
     * @return bool
     */
    public function trained() : bool
    {
        return $this->ensemble and $this->influences;
    }

    /**
     * Return the weights associated with each training sample.
     *
     * @return array
     */
    public function weights() : array
    {
        return $this->weights;
    }

    /**
     * Return the list of influence values for each classifier in the ensemble.
     *
     * @return array
     */
    public function influences() : array
    {
        return $this->influences;
    }

    /**
     * Return the average error at every epoch.
     *
     * @return array
     */
    public function steps() : array
    {
        return $this->steps;
    }

    /**
     * Train a boosted enemble of *weak* classifiers assigning an influence value
     * to each one and re-weighting the training data accordingly to reflect how
     * difficult a particular sample is to classify.
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

        DatasetIsCompatibleWithEstimator::check($dataset, $this);

        if ($this->logger) {
            $this->logger->info('Learner init ' . Params::stringify([
                'base' => $this->base,
                'estimators' => $this->estimators,
                'rate' => $this->rate,
                'ratio' => $this->ratio,
            ]));
        }

        $this->classes = $dataset->possibleOutcomes();

        $labels = $dataset->labels();
        
        $n = $dataset->numRows();
        $p = (int) round($this->ratio * $n);
        $k = count($this->classes);

        $this->weights = array_fill(0, $n, 1 / $n);

        $this->ensemble = $this->influences = $this->steps = [];

        for ($epoch = 1; $epoch <= $this->estimators; $epoch++) {
            $estimator = clone $this->base;

            $subset = $dataset->randomWeightedSubsetWithReplacement($p, $this->weights);

            $estimator->train($subset);

            $predictions = $estimator->predict($dataset);

            $loss = 0.;

            foreach ($predictions as $i => $prediction) {
                if ($prediction !== $labels[$i]) {
                    $loss += $this->weights[$i];
                }
            }

            $total = array_sum($this->weights);

            $loss /= $total;

            $influence = $this->rate
                * (log((1. - $loss) / ($loss ?: EPSILON))
                + log($k - 1));

            $this->ensemble[] = $estimator;
            $this->steps[] = $loss;
            $this->influences[] = $influence;

            if ($this->logger) {
                $this->logger->info("Epoch $epoch loss=$loss");
            }

            if (is_nan($loss) or $loss < EPSILON) {
                break 1;
            }

            foreach ($predictions as $i => $prediction) {
                if ($prediction !== $labels[$i]) {
                    $this->weights[$i] *= exp($influence);
                }
            }

            $total = array_sum($this->weights);

            foreach ($this->weights as &$weight) {
                $weight /= $total;
            }
        }

        if ($this->logger) {
            $this->logger->info('Training complete');
        }
    }

    /**
     * Make predictions from a dataset.
     *
     * @param \Rubix\ML\Datasets\Dataset $dataset
     * @throws \RuntimeException
     * @return array
     */
    public function predict(Dataset $dataset) : array
    {
        if (empty($this->ensemble) or empty($this->influences)) {
            throw new RuntimeException('The estimator has not'
                . ' been trained.');
        }
        
        return array_map('Rubix\ML\argmax', $this->score($dataset));
    }

    /**
     * Estimate probabilities for each possible outcome.
     *
     * @param \Rubix\ML\Datasets\Dataset $dataset
     * @throws \RuntimeException
     * @return array
     */
    public function proba(Dataset $dataset) : array
    {
        if (empty($this->ensemble) or empty($this->influences)) {
            throw new RuntimeException('The estimator has not'
                . ' been trained.');
        }

        $scores = $this->score($dataset);

        $probabilities = [];

        foreach ($scores as $scores) {
            $total = array_sum($scores) ?: EPSILON;

            $dist = [];

            foreach ($scores as $class => $score) {
                $dist[$class] = $score / $total;
            }

            $probabilities[] = $dist;
        }

        return $probabilities;
    }

    /**
     * Return the influence scores for each sample in the dataset.
     *
     * @param \Rubix\ML\Datasets\Dataset $dataset
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @return array
     */
    protected function score(Dataset $dataset) : array
    {
        $scores = array_fill(
            0,
            $dataset->numRows(),
            array_fill_keys($this->classes, 0.)
        );

        foreach ($this->ensemble as $i => $estimator) {
            $influence = $this->influences[$i];

            foreach ($estimator->predict($dataset) as $j => $prediction) {
                $scores[$j][$prediction] += $influence;
            }
        }

        return $scores;
    }
}
