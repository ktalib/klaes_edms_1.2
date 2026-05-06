# OSS OP → ToT Recent Mismatch Audit (PRA · IC · DR)

_Generated: 2026-04-30 12:28:24_  |  _READ-ONLY_

**Date window:** ToT `created_at` ≥ `2026-04-29 00:00:00`  
**Mismatch fields:** `plot_no`, `tp_no`, `location`, `lga`, `land_use`, `party_2` (party_1 excluded — ToT grantor is legitimately the OP grantee).  
**Normalization:** land-use prefix-folded (RES↔RESIDENTIAL, COM↔COMMERCIAL, IND↔INDUSTRIAL, AGR↔AGRICULTURE); plot_no/tp_no stripped of non-alphanumerics; case + whitespace insensitive.  
**Empty values:** treated as 'cannot compare' (do not produce a mismatch).

| Source | Rows in scope |
|---|---|
| pra ToT (in window) | 29 |
| pra OP (full)       | 139 |
| ic OP (matched)     | 14 |
| dr (matched)        | 14 |

**Mismatch pairs found: 15**

## Pairs

| prop_id | OP src | OP id | ToT id | OP temp/file | ToT temp/file | ToT created_at | mismatched fields |
|---|---|---|---|---|---|---|---|
| 57691 | ic | 1477 | 136870 | TEMP-33245 / - | TEMP-33245 / RES-2026-2061 | 2026-04-29 10:52:49.279 | party_2, land_use, plot_no, tp_no, location |
| 57744 | ic | 1531 | 137947 | TEMP-33977 / - | TEMP-33977 / RES-2026-2085 | 2026-04-30 10:55:36.552 | party_2, land_use, plot_no, tp_no, location |
| 57805 | ic | 1592 | 137610 | TEMP-33765 / - | TEMP-33765 / IND-2026-20 | 2026-04-29 16:54:07.448 | party_2, land_use, plot_no, tp_no, location |
| 69572020 | ic | 4522 | 138062 | TEMP-34056 / COM-2026-193 | TEMP-34056 / COM-2026-193 | 2026-04-30 11:28:38.388 | party_2, land_use, plot_no, tp_no, location |
| 69575030 | ic | 5277 | 137604 | TEMP-33763 / - | TEMP-33763 / IND-2026-18 | 2026-04-29 16:50:20.768 | party_2, land_use, plot_no, tp_no, location |
| 69577120 | ic | 15434 | 136863 | TEMP-33238 / - | TEMP-33238 / RES-2026-2060 | 2026-04-29 10:50:51.062 | party_2, location |
| 69577476 | ic | 15466 | 137527 | TEMP-33712 / - | TEMP-33712 / RES-2026-2074 | 2026-04-29 15:41:06.892 | party_2, plot_no, tp_no, location |
| 69578111 | ic | 15530 | 137607 | TEMP-33764 / - | TEMP-33764 / IND-2026-19 | 2026-04-29 16:52:19.201 | party_2, land_use, plot_no, tp_no, location |
| 70495 | ic | 3085 | 137612 | TEMP-33766 / - | TEMP-33766 / IND-2026-21 | 2026-04-29 16:56:02.966 | party_2, plot_no, tp_no, location |
| 80189 | ic | 3992 | 136838 | TEMP-14657 / - | TEMP-14657 / RES-2026-2058 | 2026-04-29 10:44:05.114 | party_2, location |
| 80213 | ic | 3998 | 136885 | TEMP-33249 / - | TEMP-33249 / COM-2026-200 | 2026-04-29 10:56:22.825 | party_2, location |
| 81011 | ic | 4039 | 137535 | TEMP-33714 / - | TEMP-33714 / RES-2026-2075 | 2026-04-29 15:44:24.567 | party_2, plot_no, tp_no, location |
| 87946 | ic | 4887 | 138012 | TEMP-34021 / - | TEMP-34021 / RES-2026-2087 | 2026-04-30 11:16:52.216 | party_2, land_use, plot_no, tp_no, location |
| 88138 | pra | 127410 | 137422 | TEMP-33644 / RES-2023-1005 | TEMP-33644 / RES-2023-1005 | 2026-04-29 15:05:09.254 | party_2 |
| 88180 | ic | 4936 | 137614 | TEMP-33767 / - | TEMP-33767 / RES-2026-2084 | 2026-04-29 16:57:46.965 | party_2, plot_no, tp_no, location |

## Field-level diffs

### prop_id `57691` — OP `` (id 1477, src ic) vs ToT `RES-2026-2061` (id 136870)

| field | OP value | ToT value |
|---|---|---|
| `party_2` | ALH MUNZALI ALHASSAN | MUHAMMAD ADNAN SULEIMANA |
| `land_use` | COMMERCIAL | RESIDENTIAL |
| `plot_no` | C876 | 8241 |
| `tp_no` | TP/MLPP/KBT/307D | TP/MLPP/DKD/001 |
| `location` | Plot C876, NEW DANGWAURO MARKET, Kumbotso, Kano | Other |
| _party_1 (info)_ | Kano State Government | ABDULAZIZ ISAH |
| _party_2 (info)_ | ALH MUNZALI ALHASSAN | MUHAMMAD ADNAN SULEIMANA |

### prop_id `57744` — OP `` (id 1531, src ic) vs ToT `RES-2026-2085` (id 137947)

| field | OP value | ToT value |
|---|---|---|
| `party_2` | ADAMU MUHAMMAD S RUGA | BABA BADAMASI IMAM |
| `land_use` | COMMERCIAL | RESIDENTIAL |
| `plot_no` | C405 | 850 |
| `tp_no` | TP/MLPP/KBT/307D | TP/K/338A |
| `location` | Plot C405, DAN GWAURO, NEW DANGWAURO MARKET, Kumbotso, Kano | Other |
| _party_1 (info)_ | Kano State Government | AUB |
| _party_2 (info)_ | ADAMU MUHAMMAD S RUGA | BABA BADAMASI IMAM |

### prop_id `57805` — OP `` (id 1592, src ic) vs ToT `IND-2026-20` (id 137610)

| field | OP value | ToT value |
|---|---|---|
| `party_2` | ADAMU ABUBAKAR | GARBA MJUSA DANDALAMA |
| `land_use` | RESIDENTIAL | INDUSTRIAL |
| `plot_no` | 455 | I-426 |
| `tp_no` | TP/KNUP/UC/DTF/17 | TP/K/382A |
| `location` | Plot 455, KWA, AIRFOCE KWA, Dawakin Tofa, Kano | Other |
| _party_1 (info)_ | Kano State Government | IBRAHIM ABUBAKAR |
| _party_2 (info)_ | ADAMU ABUBAKAR | GARBA MJUSA DANDALAMA |

### prop_id `69572020` — OP `COM-2026-193` (id 4522, src ic) vs ToT `COM-2026-193` (id 138062)

| field | OP value | ToT value |
|---|---|---|
| `party_2` | AMARYAWA | SD PASALI OIL AND GAS |
| `land_use` | INDUSTRIAL | COM |
| `plot_no` | TGS 94 | C-39 |
| `tp_no` | TP/KNUPDA/382 | TP/K/277B |
| `location` | Plot TGS 94, AKK TAMBURAWA, Unknown, Kano | Plot C-39, FANISAU, Ungogo, Kano |
| _party_1 (info)_ | Kano State Government | Unknown |
| _party_2 (info)_ | AMARYAWA | SD PASALI OIL AND GAS |

### prop_id `69575030` — OP `` (id 5277, src ic) vs ToT `IND-2026-18` (id 137604)

| field | OP value | ToT value |
|---|---|---|
| `party_2` | TASIU YUSUF | GARBA MJUSA DANDALAMA |
| `land_use` | RESIDENTIAL | INDUSTRIAL |
| `plot_no` | 2741A | I-425 |
| `tp_no` | TP/MLPP/TF/5B | TP/K/382A |
| `location` | Plot 2741A, LAMBU EXT, Tofa, Kano | Other |
| _party_1 (info)_ | Kano State Government | MUHAMMAD IBRAHIM |
| _party_2 (info)_ | TASIU YUSUF | GARBA MJUSA DANDALAMA |

### prop_id `69577120` — OP `` (id 15434, src ic) vs ToT `RES-2026-2060` (id 136863)

| field | OP value | ToT value |
|---|---|---|
| `party_2` | BAKUWA ABDULAZIZ | RUKAYYA MUHAMMAD SANI |
| `location` | Plot 1959, YARGAYA EXT, Dawakin Kudu, Kano | Other |
| _party_1 (info)_ | Kano State Government | BAKUWA ABDULAZIZ |
| _party_2 (info)_ | BAKUWA ABDULAZIZ | RUKAYYA MUHAMMAD SANI |

### prop_id `69577476` — OP `` (id 15466, src ic) vs ToT `RES-2026-2074` (id 137527)

| field | OP value | ToT value |
|---|---|---|
| `party_2` | ABBA AUWALU | BALA SABO |
| `plot_no` | 8672 | 1569 |
| `tp_no` | TP/MLPP/DKD/001 | TP/KN/UC/DTF/15 |
| `location` | Plot 8672, YARGAYA EXT, Dawakin Kudu, Kano | Other |
| _party_1 (info)_ | Kano State Government | IBRAHIM MUAZZAM |
| _party_2 (info)_ | ABBA AUWALU | BALA SABO |

### prop_id `69578111` — OP `` (id 15530, src ic) vs ToT `IND-2026-19` (id 137607)

| field | OP value | ToT value |
|---|---|---|
| `party_2` | IBRAHIM MUSTAPHA | GARBA MJUSA DANDALAMA |
| `land_use` | RESIDENTIAL | INDUSTRIAL |
| `plot_no` | 661 | 607 |
| `tp_no` | TP/KN/UC/DTF/16B | TP/K/382B |
| `location` | Plot 661, KAGADAMA, Dawakin Tofa, Kano | Other |
| _party_1 (info)_ | Kano State Government | DANASABE ALFA USAINI, AISHA AND 1 OTHER |
| _party_2 (info)_ | IBRAHIM MUSTAPHA | GARBA MJUSA DANDALAMA |

### prop_id `70495` — OP `` (id 3085, src ic) vs ToT `IND-2026-21` (id 137612)

| field | OP value | ToT value |
|---|---|---|
| `party_2` | ABDULKAREEM A IDRIS | GARBA MJUSA DANDALAMA |
| `plot_no` | 1106A | 1316 |
| `tp_no` | TP/KN/UC/DTF/16 | TP/K/382B |
| `location` | Plot 1106A, BAGGA, Dawakin Tofa, Kano | Other |
| _party_1 (info)_ | Kano State Government | MUHAMMAD AUWAL |
| _party_2 (info)_ | ABDULKAREEM A IDRIS | GARBA MJUSA DANDALAMA |

### prop_id `80189` — OP `` (id 3992, src ic) vs ToT `RES-2026-2058` (id 136838)

| field | OP value | ToT value |
|---|---|---|
| `party_2` | INUWA JIBRIN | NASIRU MAGAJI |
| `location` | Plot 646, BUK WESTERN BYPASS, Ungogo, Kano | BUK WESTERN BYPASS |
| _party_1 (info)_ | Kano State Government | INUWA JIBRIN |
| _party_2 (info)_ | INUWA JIBRIN | NASIRU MAGAJI |

### prop_id `80213` — OP `` (id 3998, src ic) vs ToT `COM-2026-200` (id 136885)

| field | OP value | ToT value |
|---|---|---|
| `party_2` | A MUNZALI & 2 OTHERS | BASHIR USMAN SANI |
| `location` | Plot C911B, NEW DANGWAURO MARKET, Kumbotso, Kano | Other |
| _party_1 (info)_ | Kano State Government | A MUNZALI AND 2 OTHERS |
| _party_2 (info)_ | A MUNZALI & 2 OTHERS | BASHIR USMAN SANI |

### prop_id `81011` — OP `` (id 4039, src ic) vs ToT `RES-2026-2075` (id 137535)

| field | OP value | ToT value |
|---|---|---|
| `party_2` | AUWAL SADI | BALA SABO |
| `plot_no` | 3187B | 1813 |
| `tp_no` | TP/MLPP/TF/5B | TP/KN/UC/GZ/02 |
| `location` | Plot 3187B, LAMBU EXT, Tofa, Kano | Other |
| _party_1 (info)_ | Kano State Government | MURTALA GARBA |
| _party_2 (info)_ | AUWAL SADI | BALA SABO |

### prop_id `87946` — OP `` (id 4887, src ic) vs ToT `RES-2026-2087` (id 138012)

| field | OP value | ToT value |
|---|---|---|
| `party_2` | HASHIM ISA | ALKASSIM BALARABE ABBA |
| `land_use` | INDUSTRIAL | RESIDENTIAL |
| `plot_no` | 1511 | 31B |
| `tp_no` | TP/KNUP/UC/DTF/16 | TP/K/215D |
| `location` | Plot 1511, D BAGGA, Dawakin Tofa, Kano | Other |
| _party_1 (info)_ | Kano State Government | DANLAMI SHEHU |
| _party_2 (info)_ | HASHIM ISA | ALKASSIM BALARABE ABBA |

### prop_id `88138` — OP `RES-2023-1005` (id 127410, src pra) vs ToT `RES-2023-1005` (id 137422)

| field | OP value | ToT value |
|---|---|---|
| `party_2` | ALH SANI ABUBAKAR | NAFISA ABUBAKAR |
| _party_1 (info)_ | KANO STATE GOVERNMENT | ALH SANI ABUBAKAR |
| _party_2 (info)_ | ALH SANI ABUBAKAR | NAFISA ABUBAKAR |

### prop_id `88180` — OP `` (id 4936, src ic) vs ToT `RES-2026-2084` (id 137614)

| field | OP value | ToT value |
|---|---|---|
| `party_2` | MALAM AUWALU | BABA BADAMASI IMAM |
| `plot_no` | 3084B | 849 |
| `tp_no` | TP/MLPP/TF/5B | TP/K/338A |
| `location` | Plot 3084B, LAMBU EXT, Tofa, Kano | Other |
| _party_1 (info)_ | Kano State Government | AUB |
| _party_2 (info)_ | MALAM AUWALU | BABA BADAMASI IMAM |
