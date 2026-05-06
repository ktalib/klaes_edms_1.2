# OSS OP → ToT Party / Property Mismatch Audit

_Generated: 2026-04-30 11:45:20_
_Source filter: `pra.system_source = 'OSSOPCHANGEOFNAME'`_

## 1. Screenshot Cases

### prop_id `69572020` — `TEMP-34056` / `COM-2026-193`

| pra.id | instrument_type | temp_fileno | file_no | party_1 | party_2 | land_use | plot_no | tp_no | regNo | op_serial | created_at |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 138062 | Transfer Of Title (OP) | TEMP-34056 | COM-2026-193 | Unknown | SD PASALI OIL AND GAS | COM | C-39 | TP/K/277B | 0/0/0 | 5 | 2026-04-30 11:28:38.388 |

### prop_id `87946` — `TEMP-34021` / `RES-2026-2087`

| pra.id | instrument_type | temp_fileno | file_no | party_1 | party_2 | land_use | plot_no | tp_no | regNo | op_serial | created_at |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 138012 | Transfer of Title (OP) | TEMP-34021 | RES-2026-2087 | DANLAMI SHEHU | ALKASSIM BALARABE ABBA | RESIDENTIAL | 31B | TP/K/215D | 0/0/0 | 1862 | 2026-04-30 11:16:52.216 |

## 2. All OP↔ToT Pairs With Field Mismatches

Total mismatch pairs: **131**

| prop_id | OP id | ToT id | OP temp / file | ToT temp / file | same temp? | same file? | mismatched fields |
|---|---|---|---|---|---|---|---|
| 47338 | 118188 | 118198 | TEMP-14333 / TEMP-14333 | - / RES-2018-4519 | no | no | party_1, party_2 |
| 5531 | 119527 | 126936 | TEMP-17628 / RES-2025-3942 | TEMP-17628 / RES-2025-3942 | YES | YES | party_1 |
| 57167 | 122059 | 122061 | TEMP-00711 / TEMP-00711 | TEMP-00711 / RES-2026-1784 | YES | no | party_1, party_2 |
| 57168 | 122073 | 122078 | TEMP-00712 / TEMP-00712 | TEMP-00712 / RES-2026-1785 | YES | no | party_1, party_2 |
| 57364 | 71543 | 71564 | TEMP-00908 / TEMP-00908 | TEMP-00908 / RES-2026-1637 | YES | no | party_1, party_2 |
| 57438 | 71000 | 71002 | TEMP-00982 / TEMP-00982 | TEMP-00982 / RES-2026-1618 | YES | no | party_1, party_2 |
| 57464 | 128113 | 127654 | TEMP-18224 / - | TEMP-18224 / COM-2026-142 | YES | no | party_1, party_2 |
| 57718 | 122086 | 122090 | TEMP-01250 / TEMP-01250 | TEMP-01250 / RES-2026-1786 | YES | no | party_1, party_2 |
| 57720 | 124976 | 125665 | TEMP-16734 / TEMP-16734 | TEMP-16734 / RES-2026-1888 | YES | no | party_1, party_2 |
| 57825 | 70962 | 70970 | TEMP-01355 / TEMP-01355 | TEMP-01355 / RES-2026-1610 | YES | no | party_1, party_2 |
| 57828 | 70967 | 70968 | TEMP-01358 / TEMP-01358 | TEMP-01358 / RES-2026-1609 | YES | no | party_1, party_2 |
| 57869 | 124634 | 125090 | TEMP-16680 / TEMP-16680 | TEMP-16680 / COM-2026-131 | YES | no | party_1, party_2 |
| 5788 | 119622 | 127106 | TEMP-17699 / RES-2025-2292 | TEMP-17699 / RES-2025-2292 | YES | YES | party_1, party_2 |
| 57890 | 71028 | 71029 | TEMP-01420 / TEMP-01420 | TEMP-01420 / COM-2026-114 | YES | no | party_1, party_2 |
| 58044 | 128114 | 127937 | TEMP-18225 / - | TEMP-18225 / COM-2026-161 | YES | no | party_1, party_2 |
| 5808 | 119638 | 127065 | TEMP-17679 / RES-2025-2294 | TEMP-17679 / RES-2025-2294 | YES | YES | party_1, party_2 |
| 58097 | 122099 | 122102 | TEMP-01626 / TEMP-01626 | TEMP-01626 / RES-2026-1787 | YES | no | party_1, party_2 |
| 58098 | 121995 | 122000 | TEMP-01627 / TEMP-01627 | TEMP-01627 / RES-2026-1782 | YES | no | party_1, party_2 |
| 58287 | 71342 | 71344 | TEMP-04282 / TEMP-04282 | TEMP-04282 / RES-2026-1632 | YES | no | party_1, party_2 |
| 58359 | 128115 | 127823 | TEMP-18226 / - | TEMP-18226 / COM-2026-151 | YES | no | party_1, party_2 |
| 58456 | 128116 | 127894 | TEMP-18227 / - | TEMP-18227 / COM-2026-155 | YES | no | party_1, party_2 |
| 58462 | 128117 | 127592 | TEMP-18228 / - | TEMP-18228 / COM-2026-138 | YES | no | party_1, party_2 |
| 5889 | 119732 | 130778 | TEMP-19710 / RES-2025-3684 | TEMP-19710 / RES-2025-3684 | YES | YES | party_1, party_2 |
| 59430 | 119802 | 124439 | - / RES-2025-4855 | TEMP-16494 / RES-2025-4855 | no | YES | party_1, party_2, tp_no, location |
| 6005 | 119933 | 122155 | TEMP-16047 / - | - / RES-2025-4276 | no | no | party_1, party_2 |
| 60675 | 119983 | 130745 | TEMP-19685 / RES-2025-3686 | TEMP-19685 / RES-2025-3686 | YES | YES | party_1, party_2 |
| 60735 | 119991 | 130766 | TEMP-19699 / RES-2025-3685 | TEMP-19699 / RES-2025-3685 | YES | YES | party_1, party_2 |
| 61527 | 120095 | 126885 | TEMP-17607 / COM-2023-364 | TEMP-17607 / COM-2023-364 | YES | YES | party_1 |
| 61657 | 70859 | 70862 | TEMP-05353 / TEMP-05353 | TEMP-05353 / RES-2026-1588 | YES | no | party_1 |
| 61667 | 70866 | 70867 | TEMP-05366 / TEMP-05366 | TEMP-05366 / RES-2026-1589 | YES | no | party_1 |
| 61668 | 70868 | 70869 | TEMP-05367 / TEMP-05367 | TEMP-05367 / RES-2026-1590 | YES | no | party_1 |
| 61669 | 70870 | 70871 | TEMP-05368 / TEMP-05368 | TEMP-05368 / RES-2026-1591 | YES | no | party_1 |
| 61670 | 70872 | 70873 | TEMP-05369 / TEMP-05369 | TEMP-05369 / IND-2026-8 | YES | no | party_1 |
| 61671 | 70874 | 70875 | TEMP-05370 / TEMP-05370 | TEMP-05370 / RES-2026-1592 | YES | no | party_1 |
| 61672 | 70876 | 70877 | TEMP-05371 / TEMP-05371 | TEMP-05371 / RES-2026-1593 | YES | no | party_1 |
| 62017 | 70972 | 70973 | TEMP-05409 / TEMP-05409 | TEMP-05409 / RES-2026-1611 | YES | no | party_1, party_2 |
| 62020 | 70975 | 70976 | TEMP-05410 / TEMP-05410 | TEMP-05410 / RES-2026-1612 | YES | no | party_1, party_2 |
| 62022 | 70978 | 70980 | TEMP-05411 / TEMP-05411 | TEMP-05411 / RES-2026-1613 | YES | no | party_1, party_2 |
| 62025 | 70982 | 70983 | TEMP-05412 / TEMP-05412 | TEMP-05412 / RES-2026-1614 | YES | no | party_1, party_2 |
| 62052 | 70993 | 70994 | TEMP-05418 / TEMP-05418 | TEMP-05418 / RES-2026-1615 | YES | no | party_1, party_2 |
| 62057 | 70996 | 70997 | TEMP-05420 / TEMP-05420 | TEMP-05420 / RES-2026-1616 | YES | no | party_1, party_2 |
| 62060 | 70998 | 70999 | TEMP-05422 / TEMP-05422 | TEMP-05422 / RES-2026-1617 | YES | no | party_1, party_2 |
| 62069 | 71004 | 71006 | TEMP-05423 / TEMP-05423 | TEMP-05423 / RES-2026-1619 | YES | no | party_1, party_2 |
| 62073 | 71007 | 71010 | TEMP-05427 / TEMP-05427 | TEMP-05427 / RES-2026-1620 | YES | no | party_1, party_2 |
| 62079 | 71012 | 71013 | TEMP-05429 / TEMP-05429 | TEMP-05429 / RES-2026-1621 | YES | no | party_1, party_2 |
| 62083 | 71015 | 71016 | TEMP-05431 / TEMP-05431 | TEMP-05431 / RES-2026-1622 | YES | no | party_1, party_2 |
| 62087 | 71018 | 71019 | TEMP-05433 / TEMP-05433 | TEMP-05433 / RES-2026-1623 | YES | no | party_1, party_2 |
| 62089 | 71021 | 71022 | TEMP-05435 / TEMP-05435 | TEMP-05435 / RES-2026-1624 | YES | no | party_1, party_2 |
| 62097 | 71030 | 71032 | TEMP-05440 / TEMP-05440 | TEMP-05440 / RES-2026-1625 | YES | no | party_1, party_2 |
| 62100 | 71033 | 71034 | TEMP-05443 / TEMP-05443 | TEMP-05443 / RES-2026-1626 | YES | no | party_1, party_2 |
| 62104 | 71037 | 71038 | TEMP-05445 / TEMP-05445 | TEMP-05445 / RES-2026-1628 | YES | no | party_1, party_2 |
| 62106 | 71039 | 71040 | TEMP-05446 / TEMP-05446 | TEMP-05446 / RES-2026-1629 | YES | no | party_1, party_2 |
| 62107 | 71041 | 71042 | TEMP-05447 / TEMP-05447 | TEMP-05447 / RES-2026-1630 | YES | no | party_1, party_2 |
| 62597 | 128118 | 127615 | TEMP-18229 / - | TEMP-18229 / COM-2026-140 | YES | no | party_1, party_2 |
| 62744 | 71267 | 71269 | TEMP-05587 / TEMP-05587 | TEMP-05587 / RES-2026-1631 | YES | no | party_1, party_2 |
| 63004 | 71349 | 71350 | TEMP-05635 / TEMP-05635 | TEMP-05635 / RES-2026-1633 | YES | no | party_1, party_2 |
| 63006 | 71351 | 71352 | TEMP-05660 / TEMP-05660 | TEMP-05660 / RES-2026-1634 | YES | no | party_1, party_2 |
| 63008 | 71353 | 71354 | TEMP-05662 / TEMP-05662 | TEMP-05662 / RES-2026-1635 | YES | no | party_1 |
| 63010 | 71355 | 71357 | TEMP-05663 / TEMP-05663 | TEMP-05663 / RES-2026-1636 | YES | no | party_1, party_2 |
| 63853 | 71660 | 71661 | TEMP-05855 / TEMP-05855 | TEMP-05855 / RES-2026-1639 | YES | no | party_1, party_2 |
| 63854 | 71662 | 71663 | TEMP-05857 / TEMP-05857 | TEMP-05857 / RES-2026-1640 | YES | no | party_1, party_2 |
| 63873 | 71666 | 71668 | TEMP-05214 / TEMP-05214 | - / RES-2024-3798 | no | no | party_1, party_2, land_use |
| 63920 | 71683 | 71725 | TEMP-05887 / RES-2016-1470 | - / RES-2016-1470 | no | YES | party_1, party_2 |
| 64124 | 71761 | 71763 | TEMP-05983 / TEMP-05983 | - / RES-2024-3799 | no | no | party_1, party_2, land_use |
| 64718 | 72012 | 72013 | TEMP-05873 / TEMP-05873 | TEMP-05873 / RES-2024-2293 | YES | no | party_1, party_2, land_use |
| 68923 | 77712 | 77713 | TEMP-10344 / TEMP-10344 | TEMP-10344 / RES-2026-1670 | YES | no | party_1, party_2 |
| 69572 | 114890 | 114919 | TEMP-10993 / TEMP-10993 | TEMP-10993 / RES-2026-1714 | YES | no | party_1, party_2 |
| 69573823 | 130008 | 130147 | TEMP-19227 / RES-2026-481 | TEMP-19227 / RES-2026-481 | YES | YES | party_1, party_2 |
| 69573826 | 130013 | 130109 | TEMP-19207 / RES-2026-476 | TEMP-19207 / RES-2026-476 | YES | YES | party_1 |
| 69573838 | 130021 | 130128 | TEMP-19221 / RES-2026-482 | TEMP-19221 / RES-2026-482 | YES | YES | party_1, party_2 |
| 69573839 | 130023 | 130069 | TEMP-19183 / RES-2026-480 | TEMP-19183 / RES-2026-480 | YES | YES | party_1, party_2 |
| 69573844 | 130027 | 130033 | TEMP-19158 / RES-2026-484 | TEMP-19158 / RES-2026-484 | YES | YES | party_1, party_2 |
| 69573861 | 130038 | 130053 | TEMP-19175 / RES-2026-475 | TEMP-19175 / RES-2026-475 | YES | YES | party_1, party_2, land_use, plot_no |
| 69574272 | 130355 | 130849 | TEMP-19748 / RES-2025-442 | TEMP-19748 / RES-2025-442 | YES | YES | party_1, party_2 |
| 69574351 | 130405 | 130855 | TEMP-19754 / RES-2025-3397 | TEMP-19754 / RES-2025-3397 | YES | YES | party_1, party_2 |
| 69576339 | 132549 | 135923 | TEMP-31840 / RES-2023-969 | - / RES-2023-969 | no | YES | party_1, party_2 |
| 70292 | 128119 | 127673 | TEMP-18230 / - | TEMP-18230 / COM-2026-143 | YES | no | party_1, party_2 |
| 70350 | 128120 | 127635 | TEMP-18231 / - | TEMP-18231 / COM-2026-141 | YES | no | party_1, party_2 |
| 71182 | 77547 | 77549 | TEMP-06151 / TEMP-06151 | TEMP-06151 / RES-2025-3176 | YES | no | party_1, party_2, land_use |
| 71682 | 77687 | 77851 | TEMP-11670 / TEMP-11670 | TEMP-11670 / RES-2026-1669 | YES | no | party_1, party_2 |
| 71775 | 77715 | 77718 | TEMP-11698 / TEMP-11698 | TEMP-11698 / RES-2026-1671 | YES | no | party_1, party_2 |
| 72476 | 78063 | 78065 | TEMP-11893 / TEMP-11893 | TEMP-11893 / RES-2026-1672 | YES | no | party_1, party_2 |
| 72520 | 78080 | 78081 | TEMP-11906 / TEMP-11906 | TEMP-11906 / RES-2026-1674 | YES | no | party_1, party_2 |
| 72548 | 78096 | 78379 | TEMP-11915 / TEMP-11915 | TEMP-11915 / RES-2026-1675 | YES | no | party_1, party_2 |
| 72601 | 78116 | 78119 | TEMP-11924 / TEMP-11924 | TEMP-11924 / RES-2026-1676 | YES | no | party_1, party_2 |
| 72631 | 78125 | 78126 | TEMP-11931 / TEMP-11931 | TEMP-11931 / RES-2026-1677 | YES | no | party_1, party_2 |
| 72863 | 78201 | 78203 | TEMP-12007 / TEMP-12007 | TEMP-12007 / RES-2026-1678 | YES | no | party_1, party_2 |
| 72871 | 78209 | 78373 | TEMP-06180 / TEMP-06180 | TEMP-06180 / RES-2026-1668 | YES | no | party_1, party_2, land_use |
| 73006 | 78228 | 78229 | TEMP-12062 / TEMP-12062 | TEMP-12062 / RES-2026-1681 | YES | no | party_1, party_2 |
| 73481 | 78371 | 78381 | TEMP-05384 / TEMP-05384 | TEMP-05384 / RES-2026-1684 | YES | no | party_1 |
| 73490 | 78374 | 78375 | TEMP-12150 / TEMP-12150 | TEMP-12150 / RES-2026-1683 | YES | no | party_1, party_2, land_use |
| 73491 | 78376 | 78377 | TEMP-12152 / TEMP-12152 | TEMP-12152 / RES-2026-1685 | YES | no | party_1, party_2 |
| 73748 | 78507 | 78508 | TEMP-12204 / TEMP-12204 | TEMP-12204 / RES-2026-1689 | YES | no | party_1, party_2 |
| 73901 | 128931 | 128450 | TEMP-18680 / COM-2026-171 | TEMP-18680 / COM-2026-171 | YES | YES | party_1, party_2 |
| 74027 | 78660 | 78661 | TEMP-12189 / TEMP-12189 | TEMP-12189 / COM-2026-121 | YES | no | party_1, party_2 |
| 74060 | 78671 | 78673 | TEMP-12320 / TEMP-12320 | TEMP-12320 / RES-2026-1695 | YES | no | party_1, party_2 |
| 74918 | 79229 | 79234 | TEMP-12465 / TEMP-12465 | - / RES-2026-1702 | no | no | party_1, party_2, location |
| 74931 | 79237 | 79240 | TEMP-12748 / TEMP-12748 | - / RES-2026-1703 | no | no | party_1, party_2, location |
| 74947 | 79249 | 79253 | TEMP-12753 / TEMP-12753 | - / RES-2026-1705 | no | no | party_1, party_2, location |
| 74961 | 79260 | 79262 | TEMP-12763 / TEMP-12763 | - / RES-2026-1706 | no | no | party_1, party_2, location |
| 78011 | 128937 | 128397 | TEMP-18686 / COM-2026-165 | TEMP-18686 / COM-2026-165 | YES | YES | party_1, party_2 |
| 78018 | 117008 | 120287 | TEMP-12018 / TEMP-12018 | - / RES-2025-3175 | no | no | party_1, party_2, tp_no, location |
| 78928 | 128121 | 127772 | TEMP-18232 / - | TEMP-18232 / COM-2026-147 | YES | no | party_1, party_2 |
| 79612 | 128932 | 128505 | TEMP-18681 / COM-2026-175 | TEMP-18681 / COM-2026-175 | YES | YES | party_1, party_2 |
| 79760 | 128122 | 127812 | TEMP-18233 / - | TEMP-18233 / COM-2026-150 | YES | no | party_1, party_2 |
| 80425 | 120381 | 127022 | TEMP-17660 / RES-2021-1604 | TEMP-17660 / RES-2021-1604 | YES | YES | party_1 |
| 81047 | 128123 | 127605 | TEMP-18234 / - | TEMP-18234 / COM-2026-139 | YES | no | party_1, party_2 |
| 81050 | 128933 | 128520 | TEMP-18682 / COM-2026-176 | TEMP-18682 / COM-2026-176 | YES | YES | party_1, party_2 |
| 81535 | 121059 | 121070 | TEMP-05154 / TEMP-05154 | TEMP-05154 / RES-2026-1776 | YES | no | party_1, party_2 |
| 82002 | 128934 | 128461 | TEMP-18683 / COM-2026-172 | TEMP-18683 / COM-2026-172 | YES | YES | party_1, party_2 |
| 82028 | 128124 | 127888 | TEMP-18235 / - | TEMP-18235 / COM-2026-154 | YES | no | party_1, party_2 |
| 82387 | 128935 | 128484 | TEMP-18684 / COM-2026-173 | TEMP-18684 / COM-2026-173 | YES | YES | party_1, party_2, land_use, plot_no, tp_no, lgsaOrCity |
| 83044 | 128938 | 128432 | TEMP-18687 / COM-2026-169 | TEMP-18687 / COM-2026-169 | YES | YES | party_1, party_2 |
| 83100 | 128125 | 127925 | TEMP-18236 / - | TEMP-18236 / COM-2026-160 | YES | no | party_1, party_2 |
| 83316 | 128939 | 128428 | TEMP-18688 / COM-2026-168 | TEMP-18688 / COM-2026-168 | YES | YES | party_1, party_2 |
| 83739 | 128126 | 127944 | TEMP-18237 / - | TEMP-18237 / COM-2026-162 | YES | no | party_1, party_2 |
| 84316 | 128936 | 128419 | TEMP-18685 / COM-2026-167 | TEMP-18685 / COM-2026-167 | YES | YES | party_1, party_2 |
| 84689 | 128940 | 128496 | TEMP-18689 / COM-2026-174 | TEMP-18689 / COM-2026-174 | YES | YES | party_1, party_2 |
| 85079 | 129655 | 130837 | TEMP-19743 / RES-2024-2236 | TEMP-19743 / RES-2024-2236 | YES | YES | party_1, party_2 |
| 85191 | 129747 | 130811 | TEMP-19732 / RES-2024-5562 | TEMP-19732 / RES-2024-5562 | YES | YES | party_1, party_2 |
| 85425 | 129690 | 130795 | TEMP-19723 / RES-2024-5892 | TEMP-19723 / RES-2024-5892 | YES | YES | party_1, party_2 |
| 85527 | 128127 | 127904 | TEMP-18238 / - | TEMP-18238 / COM-2026-157 | YES | no | party_1, party_2 |
| 85592 | 129632 | 130858 | TEMP-19758 / RES-2025-4304 | TEMP-19758 / RES-2025-4304 | YES | YES | party_1, party_2 |
| 85789 | 129165 | 135227 | TEMP-31894 / RES-2024-4476 | TEMP-31894 / RES-2024-4476 | YES | YES | party_1, party_2, land_use, location |
| 86003 | 128941 | 128406 | TEMP-18690 / COM-2026-166 | TEMP-18690 / COM-2026-166 | YES | YES | party_1, party_2 |
| 86527 | 128929 | 128439 | TEMP-18678 / COM-2026-170 | TEMP-18678 / COM-2026-170 | YES | YES | party_1, party_2 |
| 87399 | 126785 | 126848 | TEMP-17585 / RES-2020-270 | TEMP-17585 / RES-2020-270 | YES | YES | party_1, party_2, location |
| 87769 | 128128 | 127782 | TEMP-18239 / - | TEMP-18239 / COM-2026-148 | YES | no | party_1, party_2 |
| 88138 | 127410 | 137422 | TEMP-33644 / RES-2023-1005 | TEMP-33644 / RES-2023-1005 | YES | YES | party_1, party_2 |
| 88146 | 128129 | 127811 | TEMP-18240 / - | TEMP-18240 / COM-2026-149 | YES | no | party_1, party_2 |
| 88556 | 128930 | 128391 | TEMP-18679 / COM-2026-164 | TEMP-18679 / COM-2026-164 | YES | YES | party_1, party_2 |

## 3. Duplicate-prop_id OP Sibling Groups

Distinct `prop_id`s with more than one OP row in `pra`: **3**

### prop_id `57869` (2 OP rows)

| pra.id | temp_fileno | file_no | party_1 | party_2 | land_use | plot_no | tp_no | regNo | created_at |
|---|---|---|---|---|---|---|---|---|---|
| 124632 | TEMP-16542 | TEMP-16542 | KANO STATE GOVERNMENT | ABSENT | RESIDENTIAL | C-330 | TP/MLPP/KBT/307D | 238/238/244 | 2026-04-13 13:43:00.044 |
| 124634 | TEMP-16680 | TEMP-16680 | Kano State Government | ABSENT | RESIDENTIAL | C-330 | TP/MLPP/KBT/307D | 280/280/250 | 2026-04-13 13:53:57.694 |

### prop_id `62102` (2 OP rows)

| pra.id | temp_fileno | file_no | party_1 | party_2 | land_use | plot_no | tp_no | regNo | created_at |
|---|---|---|---|---|---|---|---|---|---|
| 71035 | TEMP-05444 | TEMP-05444 | Kano State Government | SULAIMAN ABDULKADIR | RESIDENTIAL | 6895 | TP/MLPP/DKD/001 | 14/14/234 | 2026-03-15T14:57:00 |
| 71036 | TEMP-05444 | RES-2026-1627 | SULAIMAN ABDULKADIR | BELLO MUKTAR | RES | 6895 | TP/MLPP/DKD/001 | 14/14/234 | 2026-03-15T14:58:00 |

### prop_id `69584` (2 OP rows)

| pra.id | temp_fileno | file_no | party_1 | party_2 | land_use | plot_no | tp_no | regNo | created_at |
|---|---|---|---|---|---|---|---|---|---|
| 76857 | TEMP-11005 | TEMP-11005 | KANO STATE GOVERNMENT | YUSUF ABDULRAZAQ | RES | 3812 | TP/MLPP/DKD/001 | 271/271/240 | 2026-03-22T20:03:07 |
| 115252 | TEMP-11005 | RES-2026-1730 | YUSUF ABDULRAZAQ | UMAR NAMADI INUWA | RES | 3812 | TP/MLPP/DKD/001 | 271/271/240 | 2026-03-30T08:53:56 |

## 4. Root Cause

All mismatch pairs share the same diagnostic shape:

1. The **OP** PRA row and the **ToT** PRA row carry the **same `prop_id`** (and frequently the same `temp_fileno`), even though they describe physically different properties (different `plot_no`, `land_use`, `party_2`, `tp_no`, etc.).
2. The OSS *FileNo Commissioning* flow allocated a `prop_id` against an **already-existing OP record**'s `temp_fileno` instead of creating/keeping a unique allocation per OP. This is the duplicate-`prop_id` pathology that has been logged repeatedly in `pra-write-audit.md`.
3. When the user later commissioned a Transfer of Title, the ToT linker (`MlsFileNoController` / `InstrumentCaptureService`) joined to PRA by `prop_id` only, picked the first matching OP, and inherited that OP's `temp_fileno` + `prop_id` for the ToT row, even though the ToT was actually issued for a *different* OP.
4. Result: the page shows an OP card and a ToT card under the same prop_id with completely unrelated parties, plot numbers and land uses (the screenshots).

The duplicate-prop_id resolver added in `InstrumentController::resolveOpDuplicates` prevents *new* commissions from inheriting a sibling's prop_id, but rows already written before that fix are still present and need a one-time corrective scan + relink.

## 5. Proposed One-Time Code Fix

Add an artisan command `oss:print-op-tot-mismatch` (read-only, prints all mismatched OP→ToT pairs to stdout / a CSV file in `storage/logs/`) so operations can audit them before any data correction. Pseudocode:

```php
// app/Console/Commands/PrintOpTotMismatch.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrintOpTotMismatch extends Command
{
    protected $signature = 'oss:print-op-tot-mismatch {--csv : Also write a CSV in storage/logs/}';
    protected $description = 'List all OSS OP→ToT pairs that share prop_id but have mismatched property facts.';

    public function handle(): int
    {
        $rows = DB::connection('sqlsrv')->table('pra')
            ->where('system_source', 'OSSOPCHANGEOFNAME')
            ->whereNotNull('prop_id')->where('prop_id', '!=', '')
            ->orderBy('prop_id')->orderBy('id')->get();

        $byProp = $rows->groupBy('prop_id');
        $pairs = [];
        foreach ($byProp as $pid => $group) {
            $op  = $group->first(fn($r) => stripos($r->instrument_type, 'Occupancy Permit') !== false);
            $tot = $group->first(fn($r) => stripos($r->instrument_type, 'Transfer of Title') !== false);
            if (!$op || !$tot) continue;
            $diff = collect(['party_1','party_2','land_use','plot_no','tp_no'])
                ->filter(fn($f) => strtoupper(trim($op->$f ?? '')) !== ''
                                 && strtoupper(trim($tot->$f ?? '')) !== ''
                                 && strtoupper(trim($op->$f)) !== strtoupper(trim($tot->$f)));
            if ($diff->isEmpty()) continue;
            $pairs[] = compact('pid','op','tot','diff');
        }

        $this->table(['prop_id','OP id','ToT id','OP file','ToT file','mismatched fields'],
            collect($pairs)->map(fn($p) => [
                $p['pid'], $p['op']->id, $p['tot']->id,
                $p['op']->mlsFNo ?: $p['op']->fileno,
                $p['tot']->mlsFNo ?: $p['tot']->fileno,
                $p['diff']->implode(','),
            ])->all());

        if ($this->option('csv')) {
            $path = storage_path('logs/op-tot-mismatch-' . date('Ymd-His') . '.csv');
            $fp = fopen($path, 'w');
            fputcsv($fp, ['prop_id','op_id','tot_id','op_file','tot_file','mismatched_fields','op_party_2','tot_party_2','op_plot_no','tot_plot_no','op_land_use','tot_land_use']);
            foreach ($pairs as $p) {
                fputcsv($fp, [
                    $p['pid'], $p['op']->id, $p['tot']->id,
                    $p['op']->mlsFNo ?: $p['op']->fileno,
                    $p['tot']->mlsFNo ?: $p['tot']->fileno,
                    $p['diff']->implode(','),
                    $p['op']->party_2,  $p['tot']->party_2,
                    $p['op']->plot_no,  $p['tot']->plot_no,
                    $p['op']->land_use, $p['tot']->land_use,
                ]);
            }
            fclose($fp);
            $this->info("CSV written: {$path}");
        }
        $this->info('Total mismatch pairs: ' . count($pairs));
        return self::SUCCESS;
    }
}
```

Then register it in `app/Console/Kernel.php` and run:

```bash
php artisan oss:print-op-tot-mismatch --csv
```

This **prints only** — no data is modified. Operations reviews the CSV, then a follow-up `oss:relink-op-tot` command (separate ticket) reassigns the affected ToT rows to their correct OP via `PropertyIdAllocationService::allocateOrRetrievePropId()` with `allow_temp_only=true, skip_lookup=true` (the same path the duplicate-resolver already uses for siblings).
