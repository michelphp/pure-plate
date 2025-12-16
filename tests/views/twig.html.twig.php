<?php $this->extend('base.html.twig.php'); ?>


<?php $user = "Alice"; ?>
<?php $discount = 15; ?>
<?php $initialPrice = 200; ?>
<?php $isMember = true; ?>
<?php $items = 5; ?>
<?php $bonusPoints = 50 ? $isMember : "tata"; ?>
<?php $toto = ['toto']; ?>
<?php $toto2 = new stdClass(); ?>
<?php echo $isMember ? $__filters['lower']($__filters['upper']("Eligible for members special gift ?")) : ":Regular item"; ?>

<?php $this->startBlock('body'); ?>

Hello, <?php echo $user; ?>!

<?php if ($isMember): ?>
        <?php $finalPrice = $initialPrice - ( $initialPrice * $discount / 100 ); ?>
        You have a discount of <?php echo $discount; ?>%, so the final price is <?php echo $finalPrice; ?> EUR.

        <?php if ($finalPrice < 100): ?>
                You're eligible for free shipping!
        <?php else: ?>
                Shipping costs will apply.
        <?php endif; ?>
<?php else: ?>
        No discount applies since you are not a member.
        The price remains <?php echo $initialPrice; ?> EUR.
<?php endif; ?>

<?php if ($items > 3): ?>
        As you have more than 3 items, you receive an additional 10 bonus points!
        <?php $bonusPoints = $bonusPoints + 10; ?>
<?php endif; ?>

Your total bonus points: <?php echo $bonusPoints; ?>.

<?php foreach (range(1,$items) as $i): ?>
        - Item <?php echo $i; ?>: <?php echo $isMember ? $__filters['upper']("Eligible for members special gift ?") : ":Regular item"; ?>\n
        <?php if ($i == 10): ?>
                This is an even-numbered item.
        <?php else: ?>
                This is an odd-numbered item.
        <?php endif; ?>
<?php endforeach; ?>

<?php if ($items == 0): ?>
        You have no items in your cart.
<?php elseif ($items == 1): ?>
        You have 1 item in your cart.
<?php else: ?>
        You have <?php echo $items + 41; ?> items in your cart.
<?php endif; ?>

<?php echo $__functions['dump'] ( $isMember ); ?>
<?php if ($isMember === true): ?>
<?php echo $__filters['lower']($__filters['upper']('okkkkkkkkkkkkkkkk')); ?>
<?php endif; ?>

<?php $this->endBlock(); ?>
