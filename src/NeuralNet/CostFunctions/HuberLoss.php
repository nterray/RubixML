<?php

namespace Rubix\ML\NeuralNet\CostFunctions;

use Rubix\Tensor\Tensor;
use InvalidArgumentException;

/**
 * Huber Loss
 *
 * The pseudo Huber Loss function transitions between L1 and L2 (Least Squares)
 * loss at a given pivot point (*alpha*) such that the function becomes more
 * quadratic as the loss decreases. The combination of L1 and L2 loss makes
 * Huber Loss robust to outliers while maintaining smoothness near the minimum.
 *
 * @category    Machine Learning
 * @package     Rubix/ML
 * @author      Andrew DalPino
 */
class HuberLoss implements RegressionLoss
{
    /**
     * The alpha quantile i.e the pivot point at which numbers larger will be
     * evalutated with an L1 loss while number smaller will be evalutated with
     * an L2 loss.
     *
     * @var float
     */
    protected $alpha;

    /**
     * The square of the alpha parameter.
     *
     * @var float
     */
    protected $alpha2;

    /**
     * @param float $alpha
     * @throws \InvalidArgumentException
     */
    public function __construct(float $alpha = 0.9)
    {
        if ($alpha <= 0.) {
            throw new InvalidArgumentException('Alpha must be greater than'
                . " 0, $alpha given.");
        }

        $this->alpha = $alpha;
        $this->alpha2 = $alpha ** 2;
    }

    /**
     * Return a tuple of the min and max output value for this function.
     *
     * @return float[]
     */
    public function range() : array
    {
        return [0., INF];
    }

    /**
     * Compute the loss.
     *
     * @param \Rubix\Tensor\Tensor $output
     * @param \Rubix\Tensor\Tensor $target
     * @return \Rubix\Tensor\Tensor
     */
    public function compute(Tensor $output, Tensor $target) : Tensor
    {
        return $target->subtract($output)->map([$this, '_compute']);
    }

    /**
     * Calculate the gradient of the cost function with respect to the output.
     *
     * @param \Rubix\Tensor\Tensor $output
     * @param \Rubix\Tensor\Tensor $target
     * @return \Rubix\Tensor\Tensor
     */
    public function differentiate(Tensor $output, Tensor $target) : Tensor
    {
        $alpha = $output->subtract($target);

        return $alpha->square()
            ->add($this->alpha2)
            ->pow(-0.5)
            ->multiply($alpha);
    }

    /**
     * @param float $z
     * @return float
     */
    public function _compute(float $z) : float
    {
        return $this->alpha2 * (sqrt(1. + ($z / $this->alpha) ** 2) - 1.);
    }
}
