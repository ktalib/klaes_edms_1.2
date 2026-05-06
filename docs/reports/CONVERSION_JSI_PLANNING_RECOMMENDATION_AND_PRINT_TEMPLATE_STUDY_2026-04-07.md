# Study Report: Enter JSI Report Details and Conversion Planning Recommendation Print Template

Date: 2026-04-07

## 1) Objective
This study reviews the current implementation and latest documentation for:
- Enter JSI Report Details in Conversion Applications
- Managing conversion applications for planning recommendation
- Conversion Planning Recommendation print template behavior tied to this workflow

Scope clarification:
- Print Label module is a separate workflow and is not part of Conversion Planning Recommendation.
- Any Print Label references are excluded from findings and recommendations for this study.

## 2) Latest Documentation Snapshot
Based on repository timestamps, the latest relevant documents are:

1. docs/reports/PRINT_FILE_LABEL_REGISTRIES_HOW_IT_WORKS.md (2026-03-22 20:17:04)
2. docs/reports/PRINT_FILE_LABEL_AND_TWO_RACK_SYSTEM_COMPREHENSIVE_STUDY.md (2026-03-22 16:52:20)
3. docs/guides/# Conversion Applications - Joint Site I.md (2026-03-01 19:32:36)
4. docs/guides/# Planning Recommendation for Conversion.md (2026-03-01 17:31:38)

Note: docs/guides/PLANNING_RECOMMENDATION_PRINT_QUICK_REF.md and docs/plans/PLANNING_RECOMMENDATION_PRINT_MARGINS.md exist but are currently empty.

## 3) Conversion Planning Recommendation: Current Implementation

### 3.1 Module Entry and Purpose
- Controller: app/Http/Controllers/ConversionPlanningRecommendationController.php
- View: resources/views/physical_planning/conversion/planning_recommendation.blade.php
- Route group: routes/app3.php under /physical-planning/regular/planning-recommendation

The controller index() sets:
- PageTitle: Conversion Applications - Planning Recommendation
- PageDescription: Manage conversion applications for planning recommendation

### 3.2 Data Source and Filtering
The table is powered from joint_site_inspection_reports (jsi), with conversion-specific filtering by:
- source = Conversion Applications
- file_number LIKE CON-RES%, CON-COM%, CON-IND%, CON-AG%

This is served through getData() for DataTables server-side processing.

### 3.3 Enter JSI Report Details Flow
The header action in the conversion planning recommendation view includes:
- Button label: Enter JSI Report Details
- JS action: openNewJsiReport()
- Behavior: opens the shared joint inspection modal with options:
  - isConversionFile: true
  - source: Conversion Applications

This confirms the requested conversion-specific JSI entry flow is present in UI and wired to modal open logic.

### 3.4 Existing Report Management Flow
Per-row actions:
- Non-director flow: View Inspection Details (modal in view mode)
- Director flow: Approve/Decline JSI Report (approval workbench route)

Available printing actions in script functions:
- printInspectionReport(applicationId)
- printRecommendation(applicationId)
- printScheduleOfPayment(applicationId)

## 4) Planning Recommendation Print Template (Conversion)

### 4.1 Conversion Recommendation Print Endpoint
- Route: physical-planning.conversion.recommendation.print
- Controller method: ConversionPlanningRecommendationController::printRecommendation($applicationId)
- Template: resources/views/physical_planning/conversion/print/recommendation.blade.php

### 4.2 Data Mapping in printRecommendation()
The method resolves and normalizes report data from latest JSI record for the application, including:
- applicantName
- applicationNumber (prefers file_number)
- purpose
- existing and recommended site measurements
- existing and recommended road reservation
- surrounding/recommended land use
- inspection officer cleanup

It also reads metadata embedded in existing_site_measurement_entries JSON for conversion-specific lookup details.

### 4.3 Template Output Characteristics
The conversion recommendation template is an A4 print form with:
- Ministry branding header
- Security code serial block
- tabular recommendation fields
- print button invoking window.print()

This template is specific to physical planning recommendation and is distinct from the print label template used by the file label module.

## 5) Out-of-Scope Clarification

Print Label is not part of Conversion Planning Recommendation.

This study therefore does not treat any printlabel controller, template, or routes as part of the conversion planning recommendation flow. The relevant print path for this workflow is only:
- resources/views/physical_planning/conversion/print/recommendation.blade.php
- route physical-planning.conversion.recommendation.print
- ConversionPlanningRecommendationController::printRecommendation($applicationId)

## 6) Documentation-to-Implementation Alignment

### 6.1 What aligns well
1. Conversion JSI entry button exists and is wired.
2. Conversion planning recommendation module route/controller/view are in place.
3. Conversion recommendation print path exists and is callable from UI.
4. The conversion workflow has a dedicated print recommendation template and endpoint.

### 6.2 Observed gaps
1. Empty docs:
   - docs/guides/PLANNING_RECOMMENDATION_PRINT_QUICK_REF.md
   - docs/plans/PLANNING_RECOMMENDATION_PRINT_MARGINS.md
2. The conversion guide references planned/testing items that should be re-validated against present code state before treating as fully closed.

## 7) Operational Recommendations
1. Populate the two empty planning recommendation print docs with current conversion print flow details.
2. Add a short conversion-specific print quick reference under docs/guides describing:
   - JSI report print
   - final recommendation print
   - schedule of payment print
3. Add a lightweight regression checklist for:
   - Enter JSI Report Details modal open/save
   - approval workbench transition
   - recommendation print rendering

## 8) Conclusion
The core requested flow is implemented: users can enter JSI report details in conversion planning recommendation, manage reports through review/approval, and print through the dedicated conversion recommendation template. The main improvement area is documentation freshness for this conversion workflow.

## 9) Key Files Reviewed
- app/Http/Controllers/ConversionPlanningRecommendationController.php
- resources/views/physical_planning/conversion/planning_recommendation.blade.php
- resources/views/physical_planning/conversion/print/recommendation.blade.php
- routes/app3.php
- docs/guides/# Conversion Applications - Joint Site I.md
- docs/guides/# Planning Recommendation for Conversion.md
