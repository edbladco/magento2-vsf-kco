<?php
namespace Kodbruket\VsfKco\Ui\Component\Listing\Column;

class EventActions extends \Magento\Ui\Component\Listing\Columns\Column
{
    const URL_PATH_DETAILS = 'vsfkco/event/detail';

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @param \Magento\Framework\View\Element\UiComponent\ContextInterface $context
     * @param \Magento\Framework\View\Element\UiComponentFactory           $uiComponentFactory
     * @param \Magento\Framework\UrlInterface                              $urlBuilder
     * @param array                                                        $components
     * @param array                                                        $data
     */
    public function __construct(
        \Magento\Framework\View\Element\UiComponent\ContextInterface $context,
        \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory,
        \Magento\Framework\UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                if (isset($item['event_id'])) {
                    $item[$this->getData('name')]['detail'] = [
                        'href' => $this->getEventDetailUrl($item['event_id']),
                        'label' => __('Details'),
                        'hidden' => false,
                    ];
                }
            }
        }

        return $dataSource;
    }

    /**
     * @param $eventId
     * @return string
     */
    public  function getEventDetailUrl($eventId){
        return $this->urlBuilder->getUrl(static::URL_PATH_DETAILS, ['id' => $eventId]);
    }
}
