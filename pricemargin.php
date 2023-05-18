<?php
/* Copyright (C) 2023 Mathieu Moulin            <contact@iprospective.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *   \file       mmiproduct/pricemargin.php
 *   \brief      Margin calculation helper
 */

require_once 'env.inc.php';
require_once 'main_load.inc.php';

$tab_name = 'pricemargin';
$tab_ref = 'pricemargin';

require_once '../mmicommon/producttab_1.inc.php';
