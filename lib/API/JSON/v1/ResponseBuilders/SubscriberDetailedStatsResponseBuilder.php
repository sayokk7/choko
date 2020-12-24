<?php

namespace MailPoet\Premium\API\JSON\v1\ResponseBuilders;

if (!defined('ABSPATH')) exit;


use MailPoet\Entities\NewsletterEntity;
use MailPoet\Entities\NewsletterLinkEntity;
use MailPoet\Entities\StatisticsClickEntity;
use MailPoet\Entities\StatisticsOpenEntity;
use MailPoet\Newsletter\Url as NewsletterUrl;
use MailPoet\Premium\Subscriber\Stats\SubscriberNewsletterStats;
use MailPoet\WooCommerce\Helper as WCHelper;
use MailPoet\WP\Functions as WPFunctions;

class SubscriberDetailedStatsResponseBuilder {
  const DATE_FORMAT = 'Y-m-d H:i:s';

  /** @var WPFunctions */
  private $wp;

  /** @var WCHelper */
  private $wooCommerce;

  public function __construct(WPFunctions $wp, WCHelper $wooCommerce) {
    $this->wp = $wp;
    $this->wooCommerce = $wooCommerce;
  }

  /**
   * @param SubscriberNewsletterStats[] $newslettersStats
   * @return array
   */
  public function build(array $newslettersStats): array {
    $response = [];

    foreach ($newslettersStats as $stats) {
      $item = $this->buildNewsletter($stats->getNewsletter());
      $item['actions'][] = $this->buildOpen($stats->getOpen());
      foreach ($stats->getClicks() as $click) {
        $item['actions'][] = $this->buildClick($click);
      }
      $response[] = $item;
    }
    return $response;
  }

  private function buildNewsletter(NewsletterEntity $newsletter): array {
    $sentAt = $newsletter->getSentAt();
    $previewUrl = NewsletterUrl::getViewInBrowserUrl(
      (object)[
        'id' => $newsletter->getId(),
        'hash' => $newsletter->getHash(),
      ],
      null,
      in_array($newsletter->getStatus(), [NewsletterEntity::STATUS_SENT, NewsletterEntity::STATUS_SENDING], true)
        ? $newsletter->getLatestQueue()
        : false
    );
    return [
      'id' => $newsletter->getId(),
      'preview_url' => $previewUrl,
      'subject' => $newsletter->getSubject(),
      'sent_at' => $sentAt ? $sentAt->format(self::DATE_FORMAT) : null,
      'actions' => [],
    ];
  }

  private function buildOpen(StatisticsOpenEntity $open): array {
    return [
      'id' => $open->getId(),
      'type' => 'open',
      'created_at' => $open->getCreatedAt()->format(self::DATE_FORMAT),
    ];
  }

  private function buildClick(StatisticsClickEntity $click): array {
    $link = $click->getLink();
    $linkUrl = ($link instanceof NewsletterLinkEntity) ? $link->getUrl() : '';
    $purchases = [];
    foreach ($click->getWooCommercePurchases() as $purchase) {
      $purchases[] = [
        'id' => $purchase->getId(),
        'created_at' => $purchase->getCreatedAt()->format(self::DATE_FORMAT),
        'order_id' => $purchase->getOrderId(),
        'order_url' => $this->wp->getEditPostLink($purchase->getOrderId(), 'code'),
        'revenue' => $this->wooCommerce->getRawPrice(
          $purchase->getOrderPriceTotal(),
          ['currency' => $purchase->getOrderCurrency()]
        ),
      ];
    }
    return [
      'id' => $click->getId(),
      'type' => 'click',
      'created_at' => $click->getCreatedAt()->format(self::DATE_FORMAT),
      'count' => $click->getCount(),
      'url' => $linkUrl,
      'purchases' => $purchases,
    ];
  }
}
