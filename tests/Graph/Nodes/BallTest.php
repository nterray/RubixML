<?php

namespace Rubix\ML\Tests\Graph\Nodes;

use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Graph\Nodes\Node;
use Rubix\ML\Graph\Nodes\Ball;
use Rubix\ML\Graph\Nodes\BinaryNode;
use Rubix\ML\Graph\Nodes\Hypersphere;
use Rubix\ML\Kernels\Distance\Euclidean;
use PHPUnit\Framework\TestCase;

class BallTest extends TestCase
{
    protected const COLUMN = 1;
    protected const VALUE = 3.;

    protected const SAMPLES = [
        [5., 2., -3],
        [6., 4., -5],
    ];

    protected const LABELS = [
        22, 13,
    ];

    protected const CENTER = [5.5, 3., -4];
    protected const RADIUS = 1.5;

    public function test_build_node()
    {
        $groups = [
            Labeled::quick([self::SAMPLES[0]], [self::LABELS[0]]),
            Labeled::quick([self::SAMPLES[1]], [self::LABELS[1]]),
        ];

        $node = new Ball(self::CENTER, self::RADIUS, $groups);

        $this->assertInstanceOf(Ball::class, $node);
        $this->assertInstanceOf(Hypersphere::class, $node);
        $this->assertInstanceOf(BinaryNode::class, $node);
        $this->assertInstanceOf(Node::class, $node);

        $this->assertEquals(self::CENTER, $node->center());
        $this->assertEquals(self::RADIUS, $node->radius());
        $this->assertEquals($groups, $node->groups());
    }

    public function test_split()
    {
        $dataset = Labeled::quick(self::SAMPLES, self::LABELS);

        $node = Ball::split($dataset, new Euclidean());

        $this->assertEquals(self::CENTER, $node->center());
        $this->assertEquals(self::RADIUS, $node->radius());
    }
}
