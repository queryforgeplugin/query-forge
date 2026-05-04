<?php
/**
 * Query Forge Pro — Default card template (developer reference)
 *
 * This file is reference only. To create a template that appears in the Query Forge
 * block sidebar, go to Query Forge Pro → Card Templates and add a new template (publish it).
 * End users do not need to edit files on disk.
 *
 * A copy of this file may be placed in wp-content/query-forge-templates/ for
 * developer reference; it is not used for the block dropdown.
 *
 * Available shortcodes (only output inside a Query Forge query loop):
 *
 * [qf_card background="#fff" padding="20px" radius="8px" shadow="soft" direction="vertical" gap="12px" align="left"]
 *   [qf_image size="medium" position="top" link="yes"]
 *   [qf_title tag="h3" style="font-size:18px;" link="yes"]
 *   [qf_date format="F j, Y" before="Published: " style="font-size:12px;color:#999;"]
 *   [qf_author before="By " style="font-size:12px;color:#999;"]
 *   [qf_excerpt length="20"]
 *   [qf_meta key="_price" before="$" after=" USD" fallback=""]
 *   [qf_button text="Read More" style="display:inline-block;padding:8px 16px;background:#FF9100;color:white;border-radius:4px;text-decoration:none;"]
 * [/qf_card]
 *
 * @package Query_Forge
 */
?>
[qf_card background="#ffffff" padding="20px" radius="8px" shadow="soft" gap="12px"]
[qf_image size="medium" position="top" link="yes"]
[qf_title tag="h3" style="margin:0 0 6px;font-size:18px;"]
[qf_date style="font-size:12px;color:#999;margin-bottom:8px;" before=""]
[qf_excerpt length="20"]
[qf_button text="Read More" style="display:inline-block;margin-top:10px;padding:8px 16px;background:#FF9100;color:white;border-radius:4px;text-decoration:none;font-size:13px;"]
[/qf_card]
