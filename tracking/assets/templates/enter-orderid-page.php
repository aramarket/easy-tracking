<div class="easyship-pretrack-body">
    <i class="fa-regular fa-paper-plane esyship-icon"></i>
    <p>To track your order please enter your Order ID</p>
    <form action="<?php echo get_permalink(get_option('selected_page')) ?>" method="GET" id="es-form">
        <input class="easyship-form-input" type="text" id="order_id" name="order-id" placeholder="Enter Order ID" required>
        <br>
        <button type="submit" form="es-form" class="button btn easyship-order-btn">TRACK YOUR ORDER</button>
    </form>
    <script src="https://kit.fontawesome.com/aaa59cda47.js" crossorigin="anonymous"></script>
</div>

