<div class="easyship-tracking-page">
    <div class="es-tracking-container">
        <!-- Error -->
        <?php if (!empty($error)) : ?>
            <section class="es-error-section">
                <div class="es-error">
                    <p><?php echo $error ?></p>
                </div>
            </section>
        <?php endif; ?>
        <!-- Order Details -->
        <section class="es-order-details">
            <header class="es-header es-od-header">
                <p class="es-bold"><span class="header-icon"><i class="fa-solid fa-boxes-stacked"></i></span> Order Details</p>
				<p class="es-view-order">Order #<?php echo $order_ID ?></p>
            </header>
            <article class="es-article es-od-article">
                <p> Place On <span class="es-bold"><?php echo $order_date ?></span></p>
                <table class="eashyship-table">
                    <?php foreach ($items as $item) :  
                        $product = wc_get_product($item->get_product_id());
                    ?>
                        <tr>
                            <td>
                                <a class="es-anchor" href="<?php echo get_permalink($item->get_product_id()) ?>" target="_blank">
                                    <?php 
                                        $image_id = $product->get_image_id();
                                        echo wp_get_attachment_image($image_id, 'thumbnail', false, array('class' => 'es-img', 'alt' => 'img'));
                                    ?>
                                </a>
                            </td> 
                            <td>
                                <a class="es-anchor" href="<?php echo get_permalink($item->get_product_id()) ?>" target="_blank">
                                    <?php echo (new ESTrackingFunction())->make_string_ellipsis($item->get_name(), 4); ?>
                                </a>                            
                            </td>
                            <td><?php echo $item->get_quantity().'x₹'.$item->get_total()/$item->get_quantity(); ?></td>
                            <td><?php echo '₹'.$item->get_total(); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <th></th>
                        <th></th>
                        <th>Total</th>
                        <th><?php echo '₹'.$order->get_total() ?></th>
                    </tr>
                </table>
            </article>
            <footer class="es-od-footer">
                <a href="<?php echo wc_get_account_endpoint_url('orders'); ?>"><button type="submit" class="easyship-submit-btn">Back to Orders</button></a>
            </footer>
        </section>
        <!-- End Order Details -->

        <div class="es-track-prog">
            <!-- Shipment Status -->
            <section class="es-track-details">
                <header class="es-header es-od-header">
                    <p class="es-bold"><span class="header-icon"><i class="fa-solid fa-map-location-dot"></i></span> Shipment Status</p>
                </header>
                <article class="es-article es-td-article">
                    <?php if (!empty($expectedDeliveryDate)) : ?>
                        <p class="es-small">Expected Delivery Date</p>
                        <p class="es-big" style="color:#FF5722;"><span class="es-bold"><?php echo $expectedDeliveryDate ?></span></p>
                    <?php endif; ?>
                    <p class="es-big">Shipment Status - <span class="es-bold"><?php echo $shipmentStatus ?></span></p>
                    <p class="es-small">by <span class="es-bold"><?php echo $shipThrough ?></span> on <span class="es-bold"><?php echo $shippedDate ?></span></p>
                    <p class="es-small">AWB <a class="es-anchor" href="<?php echo $trackingLink; ?>" target="_blank">#<?php echo $awbNumber ?></a></p>
                    <div class="es-track">
                        <div class="es-step <?php if ($tracking_status >= 1) { echo 'active'; } ?>"><span class="es-icon"><i class="fa fa-check"></i></span><span class="es-text">Booked</span></div>
                        <div class="es-step <?php if ($tracking_status >= 2) { echo 'active'; } ?>"><span class="es-icon"><i class="fa fa-user"></i></span><span class="es-text">Pending Pickup</span></div>
                        <div class="es-step <?php if ($tracking_status >= 3) { echo 'active'; } ?>"><span class="es-icon"><i class="fa fa-truck-fast"></i></span><span class="es-text">In-transit</span></div>
                        <div class="es-step <?php if ($tracking_status >= 4) { echo 'active'; } ?>"><span class="es-icon"> <i class="fa fa-box"></i> </span><span class="es-text">Delivered</span></div>
                    </div>
                </article>
                <footer></footer>
            </section>
            <!-- End Shipment Status -->

            <!-- Shipment Progress -->
            <section class="es-track-progress">
                <header class="es-header">
                    <p class="es-bold"><span class="header-icon"><i class="fa-solid fa-bars-progress"></i></span> Shipment Progress</p>
                </header>
                <article class="es-article es-shipment-progress">
                    <ul class="progress-bar">
                        <?php 
                            foreach ($shipmentProgress as $progress) :
                        ?>
                        <li>
                            <span class="circle"></span>
                            <p><?php echo $progress['date']; ?></p>
                            <p><span class="es-bold"><?php echo $progress['status']; ?></span><span class="es-small"> (<?php echo $progress['remark']; ?>)</span></p>
                            <p><?php echo $progress['location']; ?></p>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </article>
                <footer class="es-tracking-footer-logo">
                    <div class="es-footer-logo-div">
                        <p>Powered By </p>
                        <a href="https://easy-ship.in/" target="_blank"><img class="eashyship-logo-footer" src="<?php echo $easyshipLogo ?>" alt="easyship"></a>
                    </div>
                </footer>
            </section>
            <!-- End Shipment Progress -->
        </div>
    </div>
    <script src="https://kit.fontawesome.com/aaa59cda47.js" crossorigin="anonymous"></script>
</div>
