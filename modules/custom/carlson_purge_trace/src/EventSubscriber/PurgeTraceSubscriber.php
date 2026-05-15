<?php

namespace Drupal\carlson_purge_trace\EventSubscriber;

use Drupal\carlson_purge_trace\Service\PurgeTraceRuntime;
use Drupal\carlson_purge_trace\Service\PurgeTraceWriter;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Flushes purge trace logs and collects config and console context.
 */
class PurgeTraceSubscriber implements EventSubscriberInterface {

  /**
   * The runtime collector.
   *
   * @var \Drupal\carlson_purge_trace\Service\PurgeTraceRuntime
   */
  protected PurgeTraceRuntime $runtime;

  /**
   * The trace writer.
   *
   * @var \Drupal\carlson_purge_trace\Service\PurgeTraceWriter
   */
  protected PurgeTraceWriter $writer;

  /**
   * Constructs the subscriber.
   */
  public function __construct(
    PurgeTraceRuntime $runtime,
    PurgeTraceWriter $writer,
  ) {
    $this->runtime = $runtime;
    $this->writer = $writer;
  }

  /**
   * Records the active console command.
   */
  public function onConsoleCommand(ConsoleCommandEvent $event): void {
    $command = $event->getCommand();
    $this->runtime->setConsoleCommand($command ? $command->getName() : 'unknown');
  }

  /**
   * Records config save activity.
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    $this->runtime->recordConfigOperation('save', $event->getConfig()->getName());
  }

  /**
   * Records config delete activity.
   */
  public function onConfigDelete(ConfigCrudEvent $event): void {
    $this->runtime->recordConfigOperation('delete', $event->getConfig()->getName());
  }

  /**
   * Flushes the HTTP trace on terminate.
   */
  public function onKernelTerminate(TerminateEvent $event): void {
    $this->flush();
  }

  /**
   * Flushes the console trace on terminate.
   */
  public function onConsoleTerminate(ConsoleTerminateEvent $event): void {
    $this->flush();
  }

  /**
   * Flushes the current trace.
   */
  protected function flush(): void {
    if ($this->runtime->isFlushed()) {
      return;
    }

    $summary = $this->runtime->buildSummary();
    if ($summary !== NULL) {
      $this->writer->write($summary);
    }

    $this->runtime->markFlushed();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::SAVE => 'onConfigSave',
      ConfigEvents::DELETE => 'onConfigDelete',
      ConsoleEvents::COMMAND => 'onConsoleCommand',
      ConsoleEvents::TERMINATE => 'onConsoleTerminate',
      KernelEvents::TERMINATE => 'onKernelTerminate',
    ];
  }

}
