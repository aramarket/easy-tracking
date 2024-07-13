<?php

class TrackingModel {
    public $expectedDeliveryDate;
    public $shipmentStatus;
    public $shipThrough;
    public $shippedDate;
    public $awbNumber;
    public $trackingLink;
    public $shipmentProgress;

    public function __construct($expectedDeliveryDate, $shipmentStatus, $shipThrough, $shippedDate, $awbNumber, $trackingLink, $shipmentProgress = []) {
        $this->expectedDeliveryDate = $this->formatDate($expectedDeliveryDate);
        $this->shipmentStatus = $shipmentStatus;
        $this->shipThrough = $shipThrough;
        $this->shippedDate = $this->formatDate($shippedDate);
        $this->awbNumber = $awbNumber;
        $this->trackingLink = $trackingLink;
        $this->shipmentProgress = $shipmentProgress;
    }

    public function addShipmentProgress($date, $status, $remark, $location) {
        $this->shipmentProgress[] = [
            'date' => $this->formatDate($date),
            'status' => $status,
            'remark' => $remark,
            'location' => $location
        ];
    }

    private function formatDate($date) {
        return empty($date) || $date =='NA' ? '' : date_i18n('d, F Y', strtotime($date));
    }
}