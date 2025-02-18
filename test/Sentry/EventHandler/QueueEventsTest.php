<?php

namespace Sentry\Laravel\Tests\EventHandler;

use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Sentry\Breadcrumb;
use Sentry\Laravel\Tests\TestCase;
use function Sentry\addBreadcrumb;
use function Sentry\captureException;

class QueueEventsTest extends TestCase
{
    public function testQueueJobPushesAndPopsScopeWithBreadcrumbs(): void
    {
        dispatch(new QueueEventsTestJobWithBreadcrumb);

        $this->assertCount(0, $this->getCurrentBreadcrumbs());
    }

    public function testQueueJobThatReportsPushesAndPopsScopeWithBreadcrumbs(): void
    {
        dispatch(new QueueEventsTestJobThatReportsAnExceptionWithBreadcrumb);

        $this->assertCount(0, $this->getCurrentBreadcrumbs());

        $this->assertNotNull($this->getLastEvent());

        $event = $this->getLastEvent();

        $this->assertCount(2, $event->getBreadcrumbs());
    }

    public function testQueueJobThatThrowsLeavesPushedScopeWithBreadcrumbs(): void
    {
        try {
            dispatch(new QueueEventsTestJobThatThrowsAnUnhandledExceptionWithBreadcrumb);
        } catch (Exception $e) {
            // No action required, expected to throw
        }

        // We still expect to find the breadcrumbs from the job here so they are attached to reported exceptions

        $this->assertCount(2, $this->getCurrentBreadcrumbs());

        $firstBreadcrumb = $this->getCurrentBreadcrumbs()[0];
        $this->assertEquals('queue.job', $firstBreadcrumb->getCategory());

        $secondBreadcrumb = $this->getCurrentBreadcrumbs()[1];
        $this->assertEquals('test', $secondBreadcrumb->getCategory());
    }

    public function testQueueJobsThatThrowPopsAndPushesScopeWithBreadcrumbsBeforeNewJob(): void
    {
        try {
            dispatch(new QueueEventsTestJobThatThrowsAnUnhandledExceptionWithBreadcrumb('test #1'));
        } catch (Exception $e) {
            // No action required, expected to throw
        }

        try {
            dispatch(new QueueEventsTestJobThatThrowsAnUnhandledExceptionWithBreadcrumb('test #2'));
        } catch (Exception $e) {
            // No action required, expected to throw
        }

        // We only expect to find the breadcrumbs from the second job here

        $this->assertCount(2, $this->getCurrentBreadcrumbs());

        $firstBreadcrumb = $this->getCurrentBreadcrumbs()[0];
        $this->assertEquals('queue.job', $firstBreadcrumb->getCategory());

        $secondBreadcrumb = $this->getCurrentBreadcrumbs()[1];
        $this->assertEquals('test #2', $secondBreadcrumb->getMessage());
    }

    public function testQueueJobsWithBreadcrumbSetInBetweenKeepsNonJobBreadcrumbsOnCurrentScope(): void
    {
        dispatch(new QueueEventsTestJobWithBreadcrumb);

        addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::LEVEL_DEBUG, 'test2', 'test2'));

        dispatch(new QueueEventsTestJobWithBreadcrumb);

        $this->assertCount(1, $this->getCurrentBreadcrumbs());
    }
}

function queueEventsTestAddTestBreadcrumb($message = null): void
{
    addBreadcrumb(
        new Breadcrumb(
            Breadcrumb::LEVEL_INFO,
            Breadcrumb::LEVEL_DEBUG,
            'test',
            $message ?? 'test'
        )
    );
}

class QueueEventsTestJobWithBreadcrumb implements ShouldQueue
{
    public function handle(): void
    {
        queueEventsTestAddTestBreadcrumb();
    }
}

class QueueEventsTestJobThatReportsAnExceptionWithBreadcrumb implements ShouldQueue
{
    public function handle(): void
    {
        queueEventsTestAddTestBreadcrumb();

        captureException(new Exception('This is a test exception'));
    }
}

class QueueEventsTestJobThatThrowsAnUnhandledExceptionWithBreadcrumb implements ShouldQueue
{
    private $message;

    public function __construct($message = null)
    {
        $this->message = $message;
    }

    public function handle(): void
    {
        queueEventsTestAddTestBreadcrumb($this->message);

        throw new Exception('This is a test exception');
    }
}
