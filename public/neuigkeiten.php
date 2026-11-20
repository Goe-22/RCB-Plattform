<?php
declare(strict_types=1);
// Neuigkeiten stehen auf der Startseite; alte Links leiten dorthin weiter.
header('Location: /#neuigkeiten', true, 301);
exit;
