# OSS OP → ToT Cross-Source Mismatch Audit (PRA · IC · DR)

_Generated: 2026-04-30 11:55:15_  |  _Read-only audit (no DB writes)_

Scope: every `prop_id` that appears in `pra` with `system_source = 'OSSOPCHANGEOFNAME'`. For each prop_id, a canonical OP and canonical ToT are picked across `pra` → `ic` → `dr` (PRA preferred, then DR for ToT, then IC). Pair is reported when canonical OP and canonical ToT disagree on any of: party_1, party_2, land_use, plot_no, tp_no, lga, location.

| Source | OP rows considered | ToT rows considered |
|---|---|---|
| pra (OSSOPCHANGEOFNAME) | 139 | 367 |
| instrument_capture | 95 | 0 |
| deed_registrations | 107 | 0 |

**Mismatch pairs found: 212**

## 1. Screenshot Cases

### prop_id `69572020` — `TEMP-34056` / `COM-2026-193`

**`pra`** (1 rows)

| id | prop_id | type | temp | file | party_1 | party_2 | land_use | plot | tp_no | reg_no | created_at |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 138062 | 69572020 | Transfer Of Title (OP) | TEMP-34056 | COM-2026-193 | Unknown | SD PASALI OIL AND GAS | COM | C-39 | TP/K/277B | 0/0/0 | 2026-04-30 11:28:38.388 |

**`ic`** (1 rows)

| id | prop_id | type | temp | mlsFNo | party_1_name | party_2_name | land_use | plot | tp_no | reg_no | is_deleted | created_at |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 4522 | 69572020 | Occupancy Permit (OP) | TEMP-34056 | COM-2026-193 | Kano State Government | AMARYAWA | INDUSTRIAL | TGS 94 | TP/KNUPDA/382 | 33/33/254 | 0 | 2026-04-13 16:30:13.737 |

**`dr`** (1 rows)

| id | prop_id | type | fileno | grantor | grantee | plot | lga | reg_no | ic_id | is_deleted | created_at |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 14570 | 69572020 | Occupancy Permit (OP) | TEMP-16709 | Kano State Government | AMARYAWA |  |  | 33/33/254 | 4522 | 0 | 2026-04-13 16:30:13.7470000 |

### prop_id `87946` — `TEMP-34021` / `RES-2026-2087`

**`pra`** (1 rows)

| id | prop_id | type | temp | file | party_1 | party_2 | land_use | plot | tp_no | reg_no | created_at |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 138012 | 87946 | Transfer of Title (OP) | TEMP-34021 | RES-2026-2087 | DANLAMI SHEHU | ALKASSIM BALARABE ABBA | RESIDENTIAL | 31B | TP/K/215D | 0/0/0 | 2026-04-30 11:16:52.216 |

**`ic`** (1 rows)

| id | prop_id | type | temp | mlsFNo | party_1_name | party_2_name | land_use | plot | tp_no | reg_no | is_deleted | created_at |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 4887 | 87946 | Occupancy Permit (OP) | TEMP-34021 |  | Kano State Government | HASHIM ISA | INDUSTRIAL | 1511 | TP/KNUP/UC/DTF/16 | 77/77/255 | 0 | 2026-04-15 17:20:54.697 |

**`dr`** (1 rows)

| id | prop_id | type | fileno | grantor | grantee | plot | lga | reg_no | ic_id | is_deleted | created_at |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 14935 | 87946 | Occupancy Permit (OP) | TEMP-17786 | Kano State Government | HASHIM ISA |  |  | 77/77/255 | 4887 | 0 | 2026-04-15 17:20:54.7060000 |

## 2. All Mismatched OP↔ToT Pairs (cross-source)

Total: **212**

| prop_id | OP src | ToT src | OP id | ToT id | OP temp/file | ToT temp/file | same temp? | same file? | mismatched fields |
|---|---|---|---|---|---|---|---|---|---|
| 47338 | pra | pra | 118188 | 118198 | TEMP-14333 / TEMP-14333 | - / RES-2018-4519 | no | no | party_1, party_2 |
| 5531 | pra | pra | 119527 | 126936 | TEMP-17628 / RES-2025-3942 | TEMP-17628 / RES-2025-3942 | YES | YES | party_1 |
| 57160 | ic | pra | 711 | 135744 | TEMP-00704 / - | TEMP-00704 / RES-2026-2043 | YES | no | party_1, party_2, location |
| 57161 | ic | pra | 712 | 135742 | TEMP-00705 / - | TEMP-00705 / RES-2026-2042 | YES | no | party_1, party_2, location |
| 57162 | ic | pra | 713 | 135737 | TEMP-00706 / - | TEMP-00706 / RES-2026-2040 | YES | no | party_1, party_2, location |
| 57163 | ic | pra | 714 | 135732 | TEMP-00707 / - | TEMP-00707 / RES-2026-2039 | YES | no | party_1, party_2, location |
| 57164 | ic | pra | 715 | 135715 | TEMP-00708 / - | TEMP-00708 / RES-2026-2038 | YES | no | party_1, party_2, location |
| 57165 | ic | pra | 716 | 123776 | TEMP-00709 / - | TEMP-00709 / RES-2026-1837 | YES | no | party_1, party_2, location |
| 57166 | ic | pra | 717 | 123775 | TEMP-00710 / - | TEMP-00710 / RES-2026-1836 | YES | no | party_1, party_2, location |
| 57167 | pra | pra | 122059 | 122061 | TEMP-00711 / TEMP-00711 | TEMP-00711 / RES-2026-1784 | YES | no | party_1, party_2 |
| 57168 | pra | pra | 122073 | 122078 | TEMP-00712 / TEMP-00712 | TEMP-00712 / RES-2026-1785 | YES | no | party_1, party_2 |
| 57170 | ic | pra | 721 | 136533 | TEMP-00714 / - | TEMP-00714 / RES-2026-2050 | YES | no | party_1, party_2, location |
| 57364 | pra | pra | 71543 | 71564 | TEMP-00908 / TEMP-00908 | TEMP-00908 / RES-2026-1637 | YES | no | party_1, party_2 |
| 57438 | pra | pra | 71000 | 71002 | TEMP-00982 / TEMP-00982 | TEMP-00982 / RES-2026-1618 | YES | no | party_1, party_2 |
| 57464 | pra | pra | 128113 | 127654 | TEMP-18224 / - | TEMP-18224 / COM-2026-142 | YES | no | party_1, party_2 |
| 57691 | ic | pra | 1477 | 136870 | TEMP-33245 / - | TEMP-33245 / RES-2026-2061 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 57718 | pra | pra | 122086 | 122090 | TEMP-01250 / TEMP-01250 | TEMP-01250 / RES-2026-1786 | YES | no | party_1, party_2 |
| 57720 | pra | pra | 124976 | 125665 | TEMP-16734 / TEMP-16734 | TEMP-16734 / RES-2026-1888 | YES | no | party_1, party_2 |
| 57744 | ic | pra | 1531 | 137947 | TEMP-33977 / - | TEMP-33977 / RES-2026-2085 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 57805 | ic | pra | 1592 | 137610 | TEMP-33765 / - | TEMP-33765 / IND-2026-20 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 57825 | pra | pra | 70962 | 70970 | TEMP-01355 / TEMP-01355 | TEMP-01355 / RES-2026-1610 | YES | no | party_1, party_2 |
| 57828 | pra | pra | 70967 | 70968 | TEMP-01358 / TEMP-01358 | TEMP-01358 / RES-2026-1609 | YES | no | party_1, party_2 |
| 57869 | pra | pra | 124634 | 125090 | TEMP-16680 / TEMP-16680 | TEMP-16680 / COM-2026-131 | YES | no | party_1, party_2 |
| 5788 | pra | pra | 119622 | 127106 | TEMP-17699 / RES-2025-2292 | TEMP-17699 / RES-2025-2292 | YES | YES | party_1, party_2 |
| 57890 | pra | pra | 71028 | 71029 | TEMP-01420 / TEMP-01420 | TEMP-01420 / COM-2026-114 | YES | no | party_1, party_2 |
| 58044 | pra | pra | 128114 | 127937 | TEMP-18225 / - | TEMP-18225 / COM-2026-161 | YES | no | party_1, party_2 |
| 5808 | pra | pra | 119638 | 127065 | TEMP-17679 / RES-2025-2294 | TEMP-17679 / RES-2025-2294 | YES | YES | party_1, party_2 |
| 58096 | ic | pra | 1883 | 125093 | TEMP-01625 / - | TEMP-01625 / RES-2026-1885 | YES | no | party_1, party_2, location |
| 58097 | pra | pra | 122099 | 122102 | TEMP-01626 / TEMP-01626 | TEMP-01626 / RES-2026-1787 | YES | no | party_1, party_2 |
| 58098 | pra | pra | 121995 | 122000 | TEMP-01627 / TEMP-01627 | TEMP-01627 / RES-2026-1782 | YES | no | party_1, party_2 |
| 58287 | pra | pra | 71342 | 71344 | TEMP-04282 / TEMP-04282 | TEMP-04282 / RES-2026-1632 | YES | no | party_1, party_2 |
| 58310 | ic | pra | 2098 | 125935 | TEMP-17139 / - | TEMP-17139 / RES-2026-1902 | YES | no | party_1, party_2, location |
| 58314 | ic | pra | 2102 | 122488 | TEMP-15503 / - | TEMP-15503 / RES-2026-1793 | YES | no | party_1, party_2, plot_no, tp_no, location |
| 58335 | ic | pra | 2123 | 126844 | TEMP-04329 / - | TEMP-04329 / RES-2026-1919 | YES | no | party_1, party_2, location |
| 58359 | pra | pra | 128115 | 127823 | TEMP-18226 / - | TEMP-18226 / COM-2026-151 | YES | no | party_1, party_2 |
| 58428 | ic | pra | 2216 | 132917 | TEMP-30546 / - | TEMP-30546 / COM-2026-191 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 58456 | pra | pra | 128116 | 127894 | TEMP-18227 / - | TEMP-18227 / COM-2026-155 | YES | no | party_1, party_2 |
| 58462 | pra | pra | 128117 | 127592 | TEMP-18228 / - | TEMP-18228 / COM-2026-138 | YES | no | party_1, party_2 |
| 5889 | pra | pra | 119732 | 130778 | TEMP-19710 / RES-2025-3684 | TEMP-19710 / RES-2025-3684 | YES | YES | party_1, party_2 |
| 59430 | pra | pra | 119802 | 124439 | - / RES-2025-4855 | TEMP-16494 / RES-2025-4855 | no | YES | party_1, party_2, tp_no, location |
| 6005 | pra | pra | 119933 | 122155 | TEMP-16047 / - | - / RES-2025-4276 | no | no | party_1, party_2 |
| 60566 | ic | pra | 2590 | 129377 | TEMP-18908 / - | TEMP-18908 / COM-2026-186 | YES | no | party_1, party_2, plot_no, tp_no, location |
| 60675 | pra | pra | 119983 | 130745 | TEMP-19685 / RES-2025-3686 | TEMP-19685 / RES-2025-3686 | YES | YES | party_1, party_2 |
| 60735 | pra | pra | 119991 | 130766 | TEMP-19699 / RES-2025-3685 | TEMP-19699 / RES-2025-3685 | YES | YES | party_1, party_2 |
| 61481 | ic | pra | 2757 | 122893 | TEMP-15716 / - | TEMP-15716 / RES-2026-1818 | YES | no | party_1, party_2, plot_no, tp_no, location |
| 61527 | pra | pra | 120095 | 126885 | TEMP-17607 / COM-2023-364 | TEMP-17607 / COM-2023-364 | YES | YES | party_1 |
| 61657 | pra | pra | 70859 | 70862 | TEMP-05353 / TEMP-05353 | TEMP-05353 / RES-2026-1588 | YES | no | party_1 |
| 61667 | pra | pra | 70866 | 70867 | TEMP-05366 / TEMP-05366 | TEMP-05366 / RES-2026-1589 | YES | no | party_1 |
| 61668 | pra | pra | 70868 | 70869 | TEMP-05367 / TEMP-05367 | TEMP-05367 / RES-2026-1590 | YES | no | party_1 |
| 61669 | pra | pra | 70870 | 70871 | TEMP-05368 / TEMP-05368 | TEMP-05368 / RES-2026-1591 | YES | no | party_1 |
| 61670 | pra | pra | 70872 | 70873 | TEMP-05369 / TEMP-05369 | TEMP-05369 / IND-2026-8 | YES | no | party_1 |
| 61671 | pra | pra | 70874 | 70875 | TEMP-05370 / TEMP-05370 | TEMP-05370 / RES-2026-1592 | YES | no | party_1 |
| 61672 | pra | pra | 70876 | 70877 | TEMP-05371 / TEMP-05371 | TEMP-05371 / RES-2026-1593 | YES | no | party_1 |
| 62017 | pra | pra | 70972 | 70973 | TEMP-05409 / TEMP-05409 | TEMP-05409 / RES-2026-1611 | YES | no | party_1, party_2 |
| 62020 | pra | pra | 70975 | 70976 | TEMP-05410 / TEMP-05410 | TEMP-05410 / RES-2026-1612 | YES | no | party_1, party_2 |
| 62022 | pra | pra | 70978 | 70980 | TEMP-05411 / TEMP-05411 | TEMP-05411 / RES-2026-1613 | YES | no | party_1, party_2 |
| 62025 | pra | pra | 70982 | 70983 | TEMP-05412 / TEMP-05412 | TEMP-05412 / RES-2026-1614 | YES | no | party_1, party_2 |
| 62052 | pra | pra | 70993 | 70994 | TEMP-05418 / TEMP-05418 | TEMP-05418 / RES-2026-1615 | YES | no | party_1, party_2 |
| 62057 | pra | pra | 70996 | 70997 | TEMP-05420 / TEMP-05420 | TEMP-05420 / RES-2026-1616 | YES | no | party_1, party_2 |
| 62060 | pra | pra | 70998 | 70999 | TEMP-05422 / TEMP-05422 | TEMP-05422 / RES-2026-1617 | YES | no | party_1, party_2 |
| 62069 | pra | pra | 71004 | 71006 | TEMP-05423 / TEMP-05423 | TEMP-05423 / RES-2026-1619 | YES | no | party_1, party_2 |
| 62073 | pra | pra | 71007 | 71010 | TEMP-05427 / TEMP-05427 | TEMP-05427 / RES-2026-1620 | YES | no | party_1, party_2 |
| 62079 | pra | pra | 71012 | 71013 | TEMP-05429 / TEMP-05429 | TEMP-05429 / RES-2026-1621 | YES | no | party_1, party_2 |
| 62083 | pra | pra | 71015 | 71016 | TEMP-05431 / TEMP-05431 | TEMP-05431 / RES-2026-1622 | YES | no | party_1, party_2 |
| 62087 | pra | pra | 71018 | 71019 | TEMP-05433 / TEMP-05433 | TEMP-05433 / RES-2026-1623 | YES | no | party_1, party_2 |
| 62089 | pra | pra | 71021 | 71022 | TEMP-05435 / TEMP-05435 | TEMP-05435 / RES-2026-1624 | YES | no | party_1, party_2 |
| 62097 | pra | pra | 71030 | 71032 | TEMP-05440 / TEMP-05440 | TEMP-05440 / RES-2026-1625 | YES | no | party_1, party_2 |
| 62100 | pra | pra | 71033 | 71034 | TEMP-05443 / TEMP-05443 | TEMP-05443 / RES-2026-1626 | YES | no | party_1, party_2 |
| 62104 | pra | pra | 71037 | 71038 | TEMP-05445 / TEMP-05445 | TEMP-05445 / RES-2026-1628 | YES | no | party_1, party_2 |
| 62106 | pra | pra | 71039 | 71040 | TEMP-05446 / TEMP-05446 | TEMP-05446 / RES-2026-1629 | YES | no | party_1, party_2 |
| 62107 | pra | pra | 71041 | 71042 | TEMP-05447 / TEMP-05447 | TEMP-05447 / RES-2026-1630 | YES | no | party_1, party_2 |
| 62597 | pra | pra | 128118 | 127615 | TEMP-18229 / - | TEMP-18229 / COM-2026-140 | YES | no | party_1, party_2 |
| 62741 | ic | pra | 2841 | 126841 | TEMP-17576 / - | TEMP-17576 / RES-2026-1918 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 62744 | pra | pra | 71267 | 71269 | TEMP-05587 / TEMP-05587 | TEMP-05587 / RES-2026-1631 | YES | no | party_1, party_2 |
| 63004 | pra | pra | 71349 | 71350 | TEMP-05635 / TEMP-05635 | TEMP-05635 / RES-2026-1633 | YES | no | party_1, party_2 |
| 63006 | pra | pra | 71351 | 71352 | TEMP-05660 / TEMP-05660 | TEMP-05660 / RES-2026-1634 | YES | no | party_1, party_2 |
| 63008 | pra | pra | 71353 | 71354 | TEMP-05662 / TEMP-05662 | TEMP-05662 / RES-2026-1635 | YES | no | party_1 |
| 63010 | pra | pra | 71355 | 71357 | TEMP-05663 / TEMP-05663 | TEMP-05663 / RES-2026-1636 | YES | no | party_1, party_2 |
| 63588 | ic | pra | 2918 | 123286 | TEMP-15975 / - | TEMP-15975 / RES-2026-1827 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 63672 | ic | pra | 2927 | 129380 | TEMP-18911 / - | TEMP-18911 / COM-2026-189 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 63759 | ic | pra | 2936 | 129381 | TEMP-18912 / - | TEMP-18912 / COM-2026-190 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 63853 | pra | pra | 71660 | 71661 | TEMP-05855 / TEMP-05855 | TEMP-05855 / RES-2026-1639 | YES | no | party_1, party_2 |
| 63854 | pra | pra | 71662 | 71663 | TEMP-05857 / TEMP-05857 | TEMP-05857 / RES-2026-1640 | YES | no | party_1, party_2 |
| 63873 | pra | pra | 71666 | 71668 | TEMP-05214 / TEMP-05214 | - / RES-2024-3798 | no | no | party_1, party_2, land_use |
| 63920 | pra | pra | 71683 | 71725 | TEMP-05887 / RES-2016-1470 | - / RES-2016-1470 | no | YES | party_1, party_2 |
| 64124 | pra | pra | 71761 | 71763 | TEMP-05983 / TEMP-05983 | - / RES-2024-3799 | no | no | party_1, party_2, land_use |
| 64718 | pra | pra | 72012 | 72013 | TEMP-05873 / TEMP-05873 | TEMP-05873 / RES-2024-2293 | YES | no | party_1, party_2, land_use |
| 68923 | pra | pra | 77712 | 77713 | TEMP-10344 / TEMP-10344 | TEMP-10344 / RES-2026-1670 | YES | no | party_1, party_2 |
| 69572 | pra | pra | 114890 | 114919 | TEMP-10993 / TEMP-10993 | TEMP-10993 / RES-2026-1714 | YES | no | party_1, party_2 |
| 69572012 | ic | pra | 3070 | 135082 | TEMP-31805 / - | TEMP-31805 / RES-2026-1975 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 69572020 | ic | pra | 4522 | 138062 | TEMP-34056 / COM-2026-193 | TEMP-34056 / COM-2026-193 | YES | YES | party_1, party_2, land_use, plot_no, tp_no, location |
| 69573823 | pra | pra | 130008 | 130147 | TEMP-19227 / RES-2026-481 | TEMP-19227 / RES-2026-481 | YES | YES | party_1, party_2 |
| 69573826 | pra | pra | 130013 | 130109 | TEMP-19207 / RES-2026-476 | TEMP-19207 / RES-2026-476 | YES | YES | party_1 |
| 69573838 | pra | pra | 130021 | 130128 | TEMP-19221 / RES-2026-482 | TEMP-19221 / RES-2026-482 | YES | YES | party_1, party_2 |
| 69573839 | pra | pra | 130023 | 130069 | TEMP-19183 / RES-2026-480 | TEMP-19183 / RES-2026-480 | YES | YES | party_1, party_2 |
| 69573844 | pra | pra | 130027 | 130033 | TEMP-19158 / RES-2026-484 | TEMP-19158 / RES-2026-484 | YES | YES | party_1, party_2 |
| 69573861 | pra | pra | 130038 | 130053 | TEMP-19175 / RES-2026-475 | TEMP-19175 / RES-2026-475 | YES | YES | party_1, party_2, land_use, plot_no |
| 69574272 | pra | pra | 130355 | 130849 | TEMP-19748 / RES-2025-442 | TEMP-19748 / RES-2025-442 | YES | YES | party_1, party_2 |
| 69574351 | pra | pra | 130405 | 130855 | TEMP-19754 / RES-2025-3397 | TEMP-19754 / RES-2025-3397 | YES | YES | party_1, party_2 |
| 69575030 | ic | pra | 5277 | 137604 | TEMP-33763 / - | TEMP-33763 / IND-2026-18 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 69575318 | ic | pra | 5294 | 132921 | TEMP-30565 / - | TEMP-30565 / RES-2026-1961 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 69576339 | pra | pra | 132549 | 135923 | TEMP-31840 / RES-2023-969 | - / RES-2023-969 | no | YES | party_1, party_2 |
| 69577120 | ic | pra | 15434 | 136863 | TEMP-33238 / - | TEMP-33238 / RES-2026-2060 | YES | no | party_1, party_2, location |
| 69577476 | ic | pra | 15466 | 137527 | TEMP-33712 / - | TEMP-33712 / RES-2026-2074 | YES | no | party_1, party_2, plot_no, tp_no, location |
| 69578056 | ic | pra | 15513 | 136624 | TEMP-33037 / - | TEMP-33037 / RES-2026-2057 | YES | no | party_1, party_2, plot_no, tp_no, location |
| 69578111 | ic | pra | 15530 | 137607 | TEMP-33764 / - | TEMP-33764 / IND-2026-19 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 69579536 | ic | pra | 15621 | 135218 | TEMP-31828 / - | TEMP-31828 / COM-2026-194 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 69580168 | ic | pra | 15687 | 136084 | TEMP-32224 / - | TEMP-32224 / RES-2026-2044 | YES | no | party_1, party_2, location |
| 70292 | pra | pra | 128119 | 127673 | TEMP-18230 / - | TEMP-18230 / COM-2026-143 | YES | no | party_1, party_2 |
| 70350 | pra | pra | 128120 | 127635 | TEMP-18231 / - | TEMP-18231 / COM-2026-141 | YES | no | party_1, party_2 |
| 70495 | ic | pra | 3085 | 137612 | TEMP-33766 / - | TEMP-33766 / IND-2026-21 | YES | no | party_1, party_2, plot_no, tp_no, location |
| 71182 | pra | pra | 77547 | 77549 | TEMP-06151 / TEMP-06151 | TEMP-06151 / RES-2025-3176 | YES | no | party_1, party_2, land_use |
| 71682 | pra | pra | 77687 | 77851 | TEMP-11670 / TEMP-11670 | TEMP-11670 / RES-2026-1669 | YES | no | party_1, party_2 |
| 71775 | pra | pra | 77715 | 77718 | TEMP-11698 / TEMP-11698 | TEMP-11698 / RES-2026-1671 | YES | no | party_1, party_2 |
| 72476 | pra | pra | 78063 | 78065 | TEMP-11893 / TEMP-11893 | TEMP-11893 / RES-2026-1672 | YES | no | party_1, party_2 |
| 72520 | pra | pra | 78080 | 78081 | TEMP-11906 / TEMP-11906 | TEMP-11906 / RES-2026-1674 | YES | no | party_1, party_2 |
| 72548 | pra | pra | 78096 | 78379 | TEMP-11915 / TEMP-11915 | TEMP-11915 / RES-2026-1675 | YES | no | party_1, party_2 |
| 72601 | pra | pra | 78116 | 78119 | TEMP-11924 / TEMP-11924 | TEMP-11924 / RES-2026-1676 | YES | no | party_1, party_2 |
| 72631 | pra | pra | 78125 | 78126 | TEMP-11931 / TEMP-11931 | TEMP-11931 / RES-2026-1677 | YES | no | party_1, party_2 |
| 72786 | ic | pra | 3189 | 122700 | TEMP-11743 / - | TEMP-11743 / RES-2026-1813 | YES | no | party_1, party_2, location |
| 72802 | ic | pra | 3193 | 122793 | TEMP-11985 / - | TEMP-11985 / RES-2026-1816 | YES | no | party_1, party_2, location |
| 72841 | ic | pra | 3197 | 132907 | TEMP-30556 / - | TEMP-30556 / RES-2026-1960 | YES | no | party_1, party_2, plot_no, tp_no, location |
| 72863 | pra | pra | 78201 | 78203 | TEMP-12007 / TEMP-12007 | TEMP-12007 / RES-2026-1678 | YES | no | party_1, party_2 |
| 72871 | pra | pra | 78209 | 78373 | TEMP-06180 / TEMP-06180 | TEMP-06180 / RES-2026-1668 | YES | no | party_1, party_2, land_use |
| 72883 | ic | pra | 3216 | 122613 | TEMP-12012 / - | TEMP-12012 / RES-2026-1809 | YES | no | party_1, party_2, location |
| 72888 | ic | pra | 3221 | 122673 | TEMP-12022 / - | TEMP-12022 / RES-2026-1812 | YES | no | party_1, party_2, location |
| 73006 | pra | pra | 78228 | 78229 | TEMP-12062 / TEMP-12062 | TEMP-12062 / RES-2026-1681 | YES | no | party_1, party_2 |
| 73481 | pra | pra | 78371 | 78381 | TEMP-05384 / TEMP-05384 | TEMP-05384 / RES-2026-1684 | YES | no | party_1 |
| 73490 | pra | pra | 78374 | 78375 | TEMP-12150 / TEMP-12150 | TEMP-12150 / RES-2026-1683 | YES | no | party_1, party_2, land_use |
| 73491 | pra | pra | 78376 | 78377 | TEMP-12152 / TEMP-12152 | TEMP-12152 / RES-2026-1685 | YES | no | party_1, party_2 |
| 73493 | ic | pra | 3219 | 122660 | TEMP-12154 / - | TEMP-12154 / RES-2026-1811 | YES | no | party_1, party_2, location |
| 73748 | pra | pra | 78507 | 78508 | TEMP-12204 / TEMP-12204 | TEMP-12204 / RES-2026-1689 | YES | no | party_1, party_2 |
| 73901 | pra | pra | 128931 | 128450 | TEMP-18680 / COM-2026-171 | TEMP-18680 / COM-2026-171 | YES | YES | party_1, party_2 |
| 74021 | ic | pra | 3299 | 126851 | TEMP-17586 / - | TEMP-17586 / RES-2026-1923 | YES | no | party_1, party_2, plot_no, tp_no, location |
| 74027 | pra | pra | 78660 | 78661 | TEMP-12189 / TEMP-12189 | TEMP-12189 / COM-2026-121 | YES | no | party_1, party_2 |
| 74060 | pra | pra | 78671 | 78673 | TEMP-12320 / TEMP-12320 | TEMP-12320 / RES-2026-1695 | YES | no | party_1, party_2 |
| 74207 | ic | pra | 3331 | 122836 | TEMP-12410 / - | TEMP-12410 / RES-2026-1817 | YES | no | party_1, party_2, location |
| 74215 | ic | pra | 3333 | 122508 | TEMP-12413 / - | TEMP-12413 / RES-2026-1794 | YES | no | party_1, party_2, location |
| 74918 | pra | pra | 79229 | 79234 | TEMP-12465 / TEMP-12465 | - / RES-2026-1702 | no | no | party_1, party_2, location |
| 74931 | pra | pra | 79237 | 79240 | TEMP-12748 / TEMP-12748 | - / RES-2026-1703 | no | no | party_1, party_2, location |
| 74937 | ic | pra | 3399 | 134007 | TEMP-31317 / - | TEMP-31317 / RES-2026-1974 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 74947 | pra | pra | 79249 | 79253 | TEMP-12753 / TEMP-12753 | - / RES-2026-1705 | no | no | party_1, party_2, location |
| 74961 | pra | pra | 79260 | 79262 | TEMP-12763 / TEMP-12763 | - / RES-2026-1706 | no | no | party_1, party_2, location |
| 76597 | ic | pra | 3525 | 129367 | TEMP-18901 / - | TEMP-18901 / COM-2026-179 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 76733 | ic | pra | 3546 | 125091 | TEMP-13310 / - | TEMP-13310 / COM-2026-132 | YES | no | party_1, party_2, location |
| 76746 | ic | pra | 3558 | 125092 | TEMP-13324 / - | TEMP-13324 / RES-2026-1884 | YES | no | party_1, party_2, location |
| 76750 | ic | pra | 3562 | 122978 | TEMP-15832 / - | TEMP-15832 / RES-2026-1822 | YES | no | party_1, party_2, plot_no, tp_no, location |
| 77456 | ic | pra | 3621 | 124666 | TEMP-13543 / - | TEMP-13543 / RES-2026-1869 | YES | no | party_1, party_2, location |
| 77463 | ic | pra | 3625 | 123898 | TEMP-16280 / - | TEMP-16280 / RES-2026-1850 | YES | no | party_1, party_2, location |
| 77473 | ic | pra | 3634 | 124788 | TEMP-16616 / - | TEMP-16616 / RES-2026-1876 | YES | no | party_1, party_2, plot_no, tp_no, location |
| 77474 | ic | pra | 3635 | 124810 | TEMP-16629 / - | TEMP-16629 / RES-2026-1877 | YES | no | party_1, party_2, plot_no, tp_no, location |
| 78011 | pra | pra | 128937 | 128397 | TEMP-18686 / COM-2026-165 | TEMP-18686 / COM-2026-165 | YES | YES | party_1, party_2 |
| 78013 | ic | pra | 3723 | 124426 | TEMP-16354 / RES-2024-6383 | TEMP-16354 / RES-2024-6383 | YES | YES | party_1, party_2, land_use, plot_no, tp_no, location |
| 78018 | pra | pra | 117008 | 120287 | TEMP-12018 / TEMP-12018 | - / RES-2025-3175 | no | no | party_1, party_2, tp_no, location |
| 78415 | ic | pra | 3776 | 122321 | TEMP-15236 / - | TEMP-15236 / RES-2026-1788 | YES | no | party_1, party_2, location |
| 78420 | ic | pra | 3777 | 122418 | TEMP-13923 / - | TEMP-13923 / RES-2026-1790 | YES | no | party_1, party_2, location |
| 78423 | ic | pra | 3778 | 122428 | TEMP-13925 / - | TEMP-13925 / RES-2026-1791 | YES | no | party_1, party_2, location |
| 78432 | ic | pra | 3779 | 122386 | TEMP-13926 / - | TEMP-13926 / RES-2026-1789 | YES | no | party_1, party_2, location |
| 78679 | ic | pra | 3791 | 129371 | TEMP-18905 / - | TEMP-18905 / COM-2026-183 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 78689 | ic | pra | 3796 | 124754 | TEMP-16258 / - | TEMP-16258 / RES-2026-1874 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 78730 | ic | pra | 3802 | 123745 | TEMP-14048 / - | TEMP-14048 / RES-2026-1830 | YES | no | party_1, party_2, location |
| 78908 | ic | pra | 3825 | 129369 | TEMP-18904 / - | TEMP-18904 / COM-2026-182 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 78928 | pra | pra | 128121 | 127772 | TEMP-18232 / - | TEMP-18232 / COM-2026-147 | YES | no | party_1, party_2 |
| 79027 | ic | pra | 3839 | 123333 | TEMP-15994 / - | TEMP-15994 / IND-2026-13 | YES | no | party_1, party_2, plot_no, tp_no, location |
| 79612 | pra | pra | 128932 | 128505 | TEMP-18681 / COM-2026-175 | TEMP-18681 / COM-2026-175 | YES | YES | party_1, party_2 |
| 79723 | ic | pra | 3924 | 135696 | TEMP-32241 / - | TEMP-32241 / RES-2026-2036 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 79760 | pra | pra | 128122 | 127812 | TEMP-18233 / - | TEMP-18233 / COM-2026-150 | YES | no | party_1, party_2 |
| 80054 | ic | pra | 3970 | 122511 | TEMP-15515 / - | TEMP-15515 / RES-2026-1795 | YES | no | party_1, party_2, plot_no, tp_no, location |
| 80073 | ic | pra | 3972 | 135287 | TEMP-31890 / RES-2025-2593 | TEMP-31890 / RES-2025-2593 | YES | YES | party_2, land_use, plot_no, tp_no, location |
| 80186 | ic | pra | 3991 | 132900 | TEMP-14637 / - | TEMP-14637 / RES-2026-1959 | YES | no | party_1, party_2, location |
| 80189 | ic | pra | 3992 | 136838 | TEMP-14657 / - | TEMP-14657 / RES-2026-2058 | YES | no | party_1, party_2, location |
| 80213 | ic | pra | 3998 | 136885 | TEMP-33249 / - | TEMP-33249 / COM-2026-200 | YES | no | party_1, party_2, location |
| 80425 | pra | pra | 120381 | 127022 | TEMP-17660 / RES-2021-1604 | TEMP-17660 / RES-2021-1604 | YES | YES | party_1 |
| 80853 | ic | pra | 4007 | 123777 | TEMP-16255 / - | TEMP-16255 / RES-2026-1838 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 80882 | ic | pra | 4012 | 129375 | TEMP-18907 / - | TEMP-18907 / COM-2026-185 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 81011 | ic | pra | 4039 | 137535 | TEMP-33714 / - | TEMP-33714 / RES-2026-2075 | YES | no | party_1, party_2, plot_no, tp_no, location |
| 81047 | pra | pra | 128123 | 127605 | TEMP-18234 / - | TEMP-18234 / COM-2026-139 | YES | no | party_1, party_2 |
| 81050 | pra | pra | 128933 | 128520 | TEMP-18682 / COM-2026-176 | TEMP-18682 / COM-2026-176 | YES | YES | party_1, party_2 |
| 81535 | pra | pra | 121059 | 121070 | TEMP-05154 / TEMP-05154 | TEMP-05154 / RES-2026-1776 | YES | no | party_1, party_2 |
| 81862 | ic | pra | 4112 | 129378 | TEMP-18910 / - | TEMP-18910 / COM-2026-188 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 81947 | ic | pra | 4121 | 129373 | TEMP-18906 / - | TEMP-18906 / COM-2026-184 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 82002 | pra | pra | 128934 | 128461 | TEMP-18683 / COM-2026-172 | TEMP-18683 / COM-2026-172 | YES | YES | party_1, party_2 |
| 82028 | pra | pra | 128124 | 127888 | TEMP-18235 / - | TEMP-18235 / COM-2026-154 | YES | no | party_1, party_2 |
| 82379 | ic | pra | 4198 | 123887 | TEMP-16277 / - | TEMP-16277 / RES-2026-1847 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 82387 | pra | pra | 128935 | 128484 | TEMP-18684 / COM-2026-173 | TEMP-18684 / COM-2026-173 | YES | YES | party_1, party_2, land_use, plot_no, tp_no, lga |
| 82827 | ic | pra | 4238 | 135092 | TEMP-31810 / - | TEMP-31810 / RES-2026-1976 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 83044 | pra | pra | 128938 | 128432 | TEMP-18687 / COM-2026-169 | TEMP-18687 / COM-2026-169 | YES | YES | party_1, party_2 |
| 83100 | pra | pra | 128125 | 127925 | TEMP-18236 / - | TEMP-18236 / COM-2026-160 | YES | no | party_1, party_2 |
| 83301 | ic | pra | 4315 | 126846 | TEMP-17579 / - | TEMP-17579 / RES-2026-1922 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 83316 | pra | pra | 128939 | 128428 | TEMP-18688 / COM-2026-168 | TEMP-18688 / COM-2026-168 | YES | YES | party_1, party_2 |
| 83739 | pra | pra | 128126 | 127944 | TEMP-18237 / - | TEMP-18237 / COM-2026-162 | YES | no | party_1, party_2 |
| 83941 | ic | pra | 4382 | 127802 | TEMP-16112 / - | TEMP-16112 / RES-2026-1926 | YES | no | party_1, party_2 |
| 84316 | pra | pra | 128936 | 128419 | TEMP-18685 / COM-2026-167 | TEMP-18685 / COM-2026-167 | YES | YES | party_1, party_2 |
| 84689 | pra | pra | 128940 | 128496 | TEMP-18689 / COM-2026-174 | TEMP-18689 / COM-2026-174 | YES | YES | party_1, party_2 |
| 84965 | ic | pra | 4439 | 129366 | TEMP-18900 / - | TEMP-18900 / COM-2026-178 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 85079 | pra | pra | 129655 | 130837 | TEMP-19743 / RES-2024-2236 | TEMP-19743 / RES-2024-2236 | YES | YES | party_1, party_2 |
| 85191 | pra | pra | 129747 | 130811 | TEMP-19732 / RES-2024-5562 | TEMP-19732 / RES-2024-5562 | YES | YES | party_1, party_2 |
| 85229 | ic | pra | 4478 | 129368 | TEMP-18903 / - | TEMP-18903 / COM-2026-181 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 85425 | pra | pra | 129690 | 130795 | TEMP-19723 / RES-2024-5892 | TEMP-19723 / RES-2024-5892 | YES | YES | party_1, party_2 |
| 85527 | pra | pra | 128127 | 127904 | TEMP-18238 / - | TEMP-18238 / COM-2026-157 | YES | no | party_1, party_2 |
| 85592 | pra | pra | 129632 | 130858 | TEMP-19758 / RES-2025-4304 | TEMP-19758 / RES-2025-4304 | YES | YES | party_1, party_2 |
| 85789 | pra | pra | 129165 | 135227 | TEMP-31894 / RES-2024-4476 | TEMP-31894 / RES-2024-4476 | YES | YES | party_1, party_2, land_use, location |
| 86003 | pra | pra | 128941 | 128406 | TEMP-18690 / COM-2026-166 | TEMP-18690 / COM-2026-166 | YES | YES | party_1, party_2 |
| 86527 | pra | pra | 128929 | 128439 | TEMP-18678 / COM-2026-170 | TEMP-18678 / COM-2026-170 | YES | YES | party_1, party_2 |
| 87399 | pra | pra | 126785 | 126848 | TEMP-17585 / RES-2020-270 | TEMP-17585 / RES-2020-270 | YES | YES | party_1, party_2, location |
| 87769 | pra | pra | 128128 | 127782 | TEMP-18239 / - | TEMP-18239 / COM-2026-148 | YES | no | party_1, party_2 |
| 87946 | ic | pra | 4887 | 138012 | TEMP-34021 / - | TEMP-34021 / RES-2026-2087 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 88048 | ic | pra | 4913 | 129364 | TEMP-18817 / - | TEMP-18817 / COM-2026-177 | YES | no | party_1, party_2, land_use, plot_no, tp_no, location |
| 88138 | pra | pra | 127410 | 137422 | TEMP-33644 / RES-2023-1005 | TEMP-33644 / RES-2023-1005 | YES | YES | party_1, party_2 |
| 88146 | pra | pra | 128129 | 127811 | TEMP-18240 / - | TEMP-18240 / COM-2026-149 | YES | no | party_1, party_2 |
| 88180 | ic | pra | 4936 | 137614 | TEMP-33767 / - | TEMP-33767 / RES-2026-2084 | YES | no | party_1, party_2, plot_no, tp_no, location |
| 88556 | pra | pra | 128930 | 128391 | TEMP-18679 / COM-2026-164 | TEMP-18679 / COM-2026-164 | YES | YES | party_1, party_2 |

## 3. Pairs Grouped by Source Combination

| OP source → ToT source | pairs |
|---|---|
| ic → pra | 81 |
| pra → pra | 131 |

## 4. Duplicate-prop_id OP Siblings (same table)

Distinct prop_ids with >1 OP row in **pra**: **3**  
Distinct prop_ids with >1 OP row in **ic**: **1**

### pra · prop_id `57869` (2 OP rows)

| id | temp_fileno | file | party_1 | party_2 | land_use | plot | tp_no | created_at |
|---|---|---|---|---|---|---|---|---|
| 124632 | TEMP-16542 | TEMP-16542 | KANO STATE GOVERNMENT | ABSENT | RESIDENTIAL | C-330 | TP/MLPP/KBT/307D | 2026-04-13 13:43:00.044 |
| 124634 | TEMP-16680 | TEMP-16680 | Kano State Government | ABSENT | RESIDENTIAL | C-330 | TP/MLPP/KBT/307D | 2026-04-13 13:53:57.694 |

### pra · prop_id `62102` (2 OP rows)

| id | temp_fileno | file | party_1 | party_2 | land_use | plot | tp_no | created_at |
|---|---|---|---|---|---|---|---|---|
| 71035 | TEMP-05444 | TEMP-05444 | Kano State Government | SULAIMAN ABDULKADIR | RESIDENTIAL | 6895 | TP/MLPP/DKD/001 | 2026-03-15T14:57:00 |
| 71036 | TEMP-05444 | RES-2026-1627 | SULAIMAN ABDULKADIR | BELLO MUKTAR | RES | 6895 | TP/MLPP/DKD/001 | 2026-03-15T14:58:00 |

### pra · prop_id `69584` (2 OP rows)

| id | temp_fileno | file | party_1 | party_2 | land_use | plot | tp_no | created_at |
|---|---|---|---|---|---|---|---|---|
| 76857 | TEMP-11005 | TEMP-11005 | KANO STATE GOVERNMENT | YUSUF ABDULRAZAQ | RES | 3812 | TP/MLPP/DKD/001 | 2026-03-22T20:03:07 |
| 115252 | TEMP-11005 | RES-2026-1730 | YUSUF ABDULRAZAQ | UMAR NAMADI INUWA | RES | 3812 | TP/MLPP/DKD/001 | 2026-03-30T08:53:56 |

### ic · prop_id `57869` (2 OP rows)

| id | temp_fileno | mlsFNo | party_1_name | party_2_name | land_use | plot | tp_no | created_at |
|---|---|---|---|---|---|---|---|---|
| 1656 | TEMP-01399 |  | Kano State Government | ADAMU MUHAMMAD | RESIDENTIAL | SS 361B | TP/KNUPDA/334E | 2026-03-02 11:22:04.010 |
| 3548 | TEMP-16680 |  | Kano State Government | ABSENT | COMMERCIAL | C330 | TP/MLPP/KBT/307D | 2026-03-30 16:56:10.423 |

## 5. Root Cause

Across all three tables (`pra`, `instrument_capture`, `deed_registrations`) the affected pairs share the same pattern:

1. The OSS *FileNo Commissioning* flow saved a ToT row whose `prop_id` was inherited from an **unrelated OP** (different parties, different plot, different land use).
2. The mismatch can be cross-table: the OP card on the page may come from `pra` while the ToT it gets paired with lives in `pra` (or, less commonly, the OP from `instrument_capture` and the ToT from `pra`/`dr`).
3. `deed_registrations` rows for these ToTs use the same contaminated `prop_id` because they are written from the IC/PRA payload at registration time.
4. The page query (`OpResettlementApplicationController::index`) joins OP↔ToT by `prop_id`, so the visible card pair shows two unrelated transactions.

The duplicate-prop_id resolver added in `InstrumentController::resolveOpDuplicates` prevents *new* commissions from inheriting a sibling's prop_id (it reassigns conflicting siblings via `temp_fileno_sequence` + `PropertyIdAllocationService`). Pre-existing rows still need a one-time corrective scan.

## 6. Proposed One-Time Fix (read-only first)

Add an artisan command `oss:audit-op-tot-mismatch` that mirrors this script (PRA + IC + DR) and writes a CSV to `storage/logs/`. After ops review, a follow-up command `oss:relink-op-tot` would, **inside a transaction per pair**:

1. Identify the **correct OP** for each affected ToT by matching on `op_serial_number` + `regNo` (or `party_2`/Grantee + `plot_no`) within `instrument_capture` first, then `pra`.
2. Allocate a fresh `prop_id` and `temp_fileno` for the ToT row via `PropertyIdAllocationService::allocateOrRetrievePropId(['allow_temp_only' => true, 'skip_lookup' => true])` and `temp_fileno_sequence`.
3. Update the ToT row in **all three tables** consistently:
   - `pra`: set `prop_id` and (if `temp_fileno` was inherited) `temp_fileno`.
   - `deed_registrations`: set `prop_id` (column is `fileno`, not `temp_fileno`).
   - `instrument_capture`: set `prop_id` and `temp_fileno`.
4. Log every change to `storage/logs/op-tot-relink-YYYYMMDD.log` with old + new values for rollback.

**No DB writes will be performed without explicit ops approval.**
