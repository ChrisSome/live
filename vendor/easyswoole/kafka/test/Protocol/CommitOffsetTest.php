<?php
declare(strict_types=1);

namespace EasySwoole\test\Protocol;

use EasySwoole\Kafka\Protocol\CommitOffset;
use PHPUnit\Framework\TestCase;
use function bin2hex;
use function hex2bin;
use function json_encode;

final class CommitOffsetTest extends TestCase
{
    /**
     * @var CommitOffset
     */
    private $commit;

    public function setUp(): void
    {
        $this->commit = new CommitOffset('0.9.0.1');
    }

    /**
     * @throws \EasySwoole\Kafka\Exception\NotSupported
     * @throws \EasySwoole\Kafka\Exception\Protocol
     */
    public function testEncode(): void
    {
        $data = [
            'group_id' => 'test',
            'generation_id' => 2,
            'member_id' => 'Easyswoole-kafka-c7e3d40a-57d8-4220-9523-cebfce9a0685',
            'retention_time' => 36000,
            'data' => [
                [
                    'topic_name' => 'test',
                    'partitions' => [
                        [
                            'partition' => 0,
                            'offset' => 45,
                            'metadata' => '',
                        ],
                    ],
                ],
            ],
        ];

        $expected = '0000007f000800020000000800104561737973776f6f6c652d6b61666b610004746573740000000200354561737973776f6f6c652d6b61666b612d63376533643430612d353764382d343232302d393532332d6365626663653961303638350000000000008ca0000000010004746573740000000100000000000000000000002d0000';
        $test     = $this->commit->encode($data);

        self::assertSame($expected, bin2hex($test));
    }

    /**
     * @throws \EasySwoole\Kafka\Exception\NotSupported
     * @throws \EasySwoole\Kafka\Exception\Protocol
     */
    public function testEncodeDefault(): void
    {
        $data = [
            'group_id' => 'test',
            'data' => [
                [
                    'topic_name' => 'test',
                    'partitions' => [
                        [
                            'partition' => 0,
                            'offset' => 45,
                        ],
                    ],
                ],
            ],
        ];

        $expected = '0000004a000800020000000800104561737973776f6f6c652d6b61666b61000474657374ffffffff0000ffffffffffffffff000000010004746573740000000100000000000000000000002d0000';
        $test     = $this->commit->encode($data);

        self::assertSame($expected, bin2hex($test));
    }

    /**
     * @throws \EasySwoole\Kafka\Exception\NotSupported
     * @throws \EasySwoole\Kafka\Exception\Protocol
     */
    public function testEncodeNoData(): void
    {
        $data = ['group_id' => 'test'];

        $this->commit->encode($data);
    }

    /**
     * @throws \EasySwoole\Kafka\Exception\NotSupported
     * @throws \EasySwoole\Kafka\Exception\Protocol
     */
    public function testEncodeNoGroupId(): void
    {
        $data = [
            'data' => [],
        ];

        $this->commit->encode($data);
    }

    /**
     * @throws \EasySwoole\Kafka\Exception\NotSupported
     * @throws \EasySwoole\Kafka\Exception\Protocol
     */
    public function testEncodeNoTopicName(): void
    {
        $data = [
            'group_id' => 'test',
            'data' => [
                [],
            ],
        ];

        $this->commit->encode($data);
    }

    /**
     * @throws \EasySwoole\Kafka\Exception\NotSupported
     * @throws \EasySwoole\Kafka\Exception\Protocol
     */
    public function testEncodeNoPartitions(): void
    {
        $data = [
            'group_id' => 'test',
            'data' => [
                ['topic_name' => 'test'],
            ],
        ];

        $this->commit->encode($data);
    }

    /**
     * @throws \EasySwoole\Kafka\Exception\NotSupported
     * @throws \EasySwoole\Kafka\Exception\Protocol
     */
    public function testEncodeNoPartition(): void
    {
        $data = [
            'group_id' => 'test',
            'data' => [
                [
                    'topic_name' => 'test',
                    'partitions' => [
                        [],
                    ],
                ],
            ],
        ];

        $this->commit->encode($data);
    }

    /**
     * @throws \EasySwoole\Kafka\Exception\NotSupported
     * @throws \EasySwoole\Kafka\Exception\Protocol
     */
    public function testEncodeNoOffset(): void
    {
        $data = [
            'group_id' => 'test',
            'data' => [
                [
                    'topic_name' => 'test',
                    'partitions' => [
                        ['partition' => 0],
                    ],
                ],
            ],
        ];

        $this->commit->encode($data);
    }

    /**
     * @throws \EasySwoole\Kafka\Exception\Exception
     */
    public function testDecode(): void
    {
        $data     = '0000000100047465737400000001000000000000';
        $expected = '[{"topicName":"test","partitions":[{"partition":0,"errorCode":0}]}]';

        $test = $this->commit->decode(hex2bin($data));
        self::assertJsonStringEqualsJsonString($expected, json_encode($test));
    }
}
