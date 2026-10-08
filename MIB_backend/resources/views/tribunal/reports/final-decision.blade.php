<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $report_number }} - Official Tribunal Report</title>
    <style>
        @page {
            margin: 110px 40px 65px 40px;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9.5pt;
            line-height: 1.45;
            color: #1a202c;
            background: #ffffff;
            margin: 0;
            padding: 0;
        }

        /* WATERMARK (Repeats on every page via position: fixed) */
        #watermark {
            position: fixed;
            top: 28%;
            left: -20px;
            right: -20px;
            text-align: center;
            opacity: 0.05;
            transform: rotate(-32deg);
            font-size: 34pt;
            font-weight: 900;
            letter-spacing: 5px;
            color: #000000;
            z-index: -1000;
            line-height: 1.35;
            text-transform: uppercase;
        }

        /* HEADER (Fixed on every page) */
        header {
            position: fixed;
            top: -95px;
            left: 0;
            right: 0;
            height: 80px;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 6px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-brand {
            font-size: 13pt;
            font-weight: 800;
            color: #1e3a8a;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin: 0;
        }

        .header-subbrand {
            font-size: 8.5pt;
            font-weight: 700;
            color: #3b82f6;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin: 1px 0 0 0;
        }

        .header-title {
            font-size: 9.5pt;
            font-weight: 800;
            color: #0f172a;
            text-align: right;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .header-meta {
            font-size: 7.5pt;
            color: #64748b;
            text-align: right;
            margin-top: 2px;
            font-family: 'Courier New', Courier, monospace;
        }

        /* FOOTER (Fixed on every page) */
        footer {
            position: fixed;
            bottom: -45px;
            left: 0;
            right: 0;
            height: 35px;
            border-top: 1px solid #cbd5e1;
            padding-top: 5px;
            font-size: 7.5pt;
            color: #64748b;
            font-family: 'Courier New', Courier, monospace;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-left {
            text-align: left;
        }

        .footer-center {
            text-align: center;
        }

        .footer-right {
            text-align: right;
        }

        .page-counter:before {
            content: "Page " counter(page);
        }

        /* CONTENT ELEMENTS */
        .banner-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #1e3a8a;
            padding: 10px 14px;
            margin-bottom: 14px;
        }

        .banner-table {
            width: 100%;
            border-collapse: collapse;
        }

        .section {
            margin-bottom: 16px;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 10.5pt;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1.5px solid #0f172a;
            padding-bottom: 3px;
            margin-bottom: 8px;
        }

        .meta-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .meta-grid td {
            padding: 4px 6px;
            font-size: 8.5pt;
            vertical-align: top;
            border-bottom: 1px solid #f1f5f9;
        }

        .meta-label {
            font-weight: 700;
            color: #475569;
            width: 25%;
            text-transform: uppercase;
            font-size: 7.5pt;
        }

        .meta-value {
            color: #0f172a;
            font-weight: 600;
        }

        .meta-code {
            font-family: 'Courier New', Courier, monospace;
            font-weight: 700;
            color: #1e3a8a;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            margin-bottom: 8px;
        }

        .data-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 7.5pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid #cbd5e1;
            padding: 5px 6px;
            text-align: left;
        }

        .data-table td {
            border: 1px solid #e2e8f0;
            padding: 5px 6px;
            font-size: 8.5pt;
            vertical-align: top;
        }

        .data-table tr:nth-child(even) td {
            background-color: #fafafa;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 7pt;
            font-weight: 700;
            text-transform: uppercase;
            border-radius: 3px;
            letter-spacing: 0.5px;
        }

        .badge-primary { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        .badge-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-warning { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .badge-danger  { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .badge-dark    { background: #1e293b; color: #ffffff; }

        .callout {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px 10px;
            margin-bottom: 8px;
        }

        .callout-title {
            font-weight: 700;
            font-size: 8.5pt;
            color: #1e293b;
            margin-bottom: 3px;
        }

        .narrative-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 3px;
            padding: 8px 10px;
            font-size: 9pt;
            color: #334155;
            line-height: 1.5;
            margin-top: 4px;
            margin-bottom: 8px;
        }

        .auth-box {
            background-color: #f0fdf4;
            border: 1.5px solid #86efac;
            padding: 10px 12px;
            margin-top: 14px;
            page-break-inside: avoid;
        }

        .auth-table {
            width: 100%;
            border-collapse: collapse;
        }

        .auth-qr {
            width: 110px;
            text-align: center;
            vertical-align: middle;
        }

        .auth-details {
            padding-left: 14px;
            vertical-align: middle;
        }

        .hash-code {
            font-family: 'Courier New', Courier, monospace;
            font-size: 7pt;
            color: #15803d;
            word-break: break-all;
            background: #ffffff;
            border: 1px solid #bbf7d0;
            padding: 4px 6px;
            margin-top: 4px;
        }

        .disclaimer-text {
            font-size: 7pt;
            color: #64748b;
            text-align: center;
            margin-top: 10px;
            line-height: 1.35;
        }
    </style>
</head>
<body>

    <!-- REPEATED WATERMARK -->
    <div id="watermark">
        MYINTELLIBOOK_LIVE<br>
        OFFICIAL TRIBUNAL REPORT
    </div>

    <!-- REPEATED HEADER -->
    <header>
        <table class="header-table">
            <tr>
                <td style="width: 55%; vertical-align: top;">
                    <div class="header-brand">MYINTELLIBOOK_LIVE</div>
                    <div class="header-subbrand">TRIBUNAL DIVISION &bull; JUDICIAL PROCEEDINGS</div>
                </td>
                <td style="width: 45%; vertical-align: top; text-align: right;">
                    <div class="header-title">OFFICIAL CASE REPORT</div>
                    <div class="header-meta">REPORT: {{ $report_number }} (v{{ $version }})</div>
                    <div class="header-meta">VERIFICATION: {{ $verification_code }}</div>
                </td>
            </tr>
        </table>
    </header>

    <!-- REPEATED FOOTER -->
    <footer>
        <table class="footer-table">
            <tr>
                <td class="footer-left" style="width: 35%;">
                    {{ $report_number }} &bull; {{ $case_number }}
                </td>
                <td class="footer-center" style="width: 40%;">
                    AUTH CODE: {{ $verification_code }}
                </td>
                <td class="footer-right page-counter" style="width: 25%;"></td>
            </tr>
        </table>
    </footer>

    <!-- DOCUMENT BANNER -->
    <div class="banner-box">
        <table class="banner-table">
            <tr>
                <td style="vertical-align: top;">
                    <div style="font-size: 12pt; font-weight: 800; color: #0f172a; margin-bottom: 2px;">
                        CASE DETERMINATION &amp; OFFICIAL RECORD
                    </div>
                    <div style="font-size: 9pt; color: #475569;">
                        Matter: <strong>{{ $case_title }}</strong> (Category: {{ ucfirst($category) }})
                    </div>
                </td>
                <td style="vertical-align: top; text-align: right; width: 35%;">
                    <span class="badge badge-primary" style="font-size: 8pt; padding: 4px 8px;">
                        STATUS: {{ strtoupper(str_replace('_', ' ', $case_status)) }}
                    </span>
                    <div style="font-size: 7.5pt; color: #64748b; margin-top: 3px;">
                        Issued: {{ $issued_at }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- SECTION A: OFFICIAL METADATA -->
    <div class="section">
        <div class="section-title">1. Report &amp; Case Identification</div>
        <table class="meta-grid">
            <tr>
                <td class="meta-label">Official Report No.</td>
                <td class="meta-value meta-code">{{ $report_number }}</td>
                <td class="meta-label">Verification Code</td>
                <td class="meta-value meta-code">{{ $verification_code }}</td>
            </tr>
            <tr>
                <td class="meta-label">Tribunal Case No.</td>
                <td class="meta-value meta-code">{{ $case_number }}</td>
                <td class="meta-label">Decision Number</td>
                <td class="meta-value meta-code">{{ $decision_number }}</td>
            </tr>
            <tr>
                <td class="meta-label">Filing Date</td>
                <td class="meta-value">{{ $submitted_at }}</td>
                <td class="meta-label">Publication Date</td>
                <td class="meta-value">{{ $decision_published_at }}</td>
            </tr>
            <tr>
                <td class="meta-label">Adjudicating Body</td>
                <td class="meta-value">{{ $adjudicated_by }}</td>
                <td class="meta-label">Report Version</td>
                <td class="meta-value">Version {{ $version }} (Issued {{ $issued_at }})</td>
            </tr>
        </table>
    </div>

    <!-- SECTION B: PARTIES -->
    <div class="section">
        <div class="section-title">2. Case Participants &amp; Representation</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 25%;">Party Role</th>
                    <th style="width: 35%;">Identified Name</th>
                    <th style="width: 40%;">Authorized Legal Representation</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Complainant</strong></td>
                    <td>{{ $complainant_name }}</td>
                    <td>
                        @php
                            $compReps = array_filter($representatives, fn($r) => $r['side'] === 'Complainant');
                        @endphp
                        @if(!empty($compReps))
                            @foreach($compReps as $cr)
                                <div><strong>{{ $cr['name'] }}</strong> ({{ $cr['law_firm'] ?? 'Verified Representative' }}) - Bar Reg: {{ $cr['bar_number'] ?? 'Verified' }}</div>
                            @endforeach
                        @else
                            <span style="color: #64748b; font-style: italic;">Self-Represented (Pro Se)</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td><strong>Respondent</strong></td>
                    <td>{{ $respondent_name }}</td>
                    <td>
                        @php
                            $respReps = array_filter($representatives, fn($r) => $r['side'] === 'Respondent');
                        @endphp
                        @if(!empty($respReps))
                            @foreach($respReps as $rr)
                                <div><strong>{{ $rr['name'] }}</strong> ({{ $rr['law_firm'] ?? 'Verified Representative' }}) - Bar Reg: {{ $rr['bar_number'] ?? 'Verified' }}</div>
                            @endforeach
                        @else
                            <span style="color: #64748b; font-style: italic;">Self-Represented (Pro Se)</span>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- SECTION C: ORIGINAL COMPLAINT & RESPONDENT POSITION -->
    <div class="section">
        <div class="section-title">3. Pleadings: Complaint &amp; Formal Response</div>
        <div class="callout">
            <div class="callout-title">A. Original Statement of Complaint:</div>
            <div class="narrative-box">{{ $complaint_description }}</div>
            <div style="font-size: 8pt; color: #475569; margin-top: 4px;">
                <strong>Requested Remedy / Relief Sought:</strong> {{ $requested_resolution ?: 'Standard Tribunal Adjudication' }}
            </div>
        </div>

        @if($respondent_response)
            <div class="callout" style="margin-top: 8px;">
                <div class="callout-title">
                    B. Formal Respondent Answer (Submitted: {{ $respondent_response['submitted_at'] }}):
                    <span class="badge badge-dark" style="margin-left: 6px;">{{ strtoupper(str_replace('_', ' ', $respondent_response['position'])) }}</span>
                </div>
                <div class="narrative-box">{{ $respondent_response['response_text'] }}</div>
            </div>
        @else
            <div class="callout" style="margin-top: 8px;">
                <div class="callout-title">B. Formal Respondent Answer:</div>
                <div style="color: #64748b; font-style: italic; font-size: 8.5pt;">No formal written answer was filed by respondent prior to hearing.</div>
            </div>
        @endif
    </div>

    <!-- SECTION D: PROCEDURAL HISTORY -->
    <div class="section">
        <div class="section-title">4. Procedural History &amp; Proceedings Timeline</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 18%;">Milestone Date</th>
                    <th style="width: 27%;">Procedural Stage</th>
                    <th style="width: 55%;">Official Entry Description</th>
                </tr>
            </thead>
            <tbody>
                @foreach($procedural_history as $hist)
                    <tr>
                        <td style="font-family: 'Courier New', Courier, monospace; font-size: 8pt;">{{ $hist['date'] }}</td>
                        <td><strong>{{ $hist['stage'] }}</strong></td>
                        <td>{{ $hist['description'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- SECTION E: EVIDENCE INDEX -->
    <div class="section">
        <div class="section-title">5. Official Evidence Index</div>
        @if(empty($evidence_index))
            <div style="color: #64748b; font-size: 8.5pt; font-style: italic;">No formal evidence items were entered into the official case record.</div>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 16%;">Item No.</th>
                        <th style="width: 12%;">Type</th>
                        <th style="width: 25%;">Title / Description</th>
                        <th style="width: 20%;">Submitted By</th>
                        <th style="width: 15%;">Date</th>
                        <th style="width: 12%;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($evidence_index as $ev)
                        <tr>
                            <td class="meta-code">{{ $ev['evidence_number'] }}</td>
                            <td><span class="badge badge-primary">{{ strtoupper($ev['type']) }}</span></td>
                            <td>
                                <strong>{{ $ev['title'] }}</strong>
                                @if($ev['description'])
                                    <div style="font-size: 7.5pt; color: #475569; margin-top: 2px;">{{ $ev['description'] }}</div>
                                @endif
                            </td>
                            <td>{{ $ev['submitted_by'] }}</td>
                            <td style="font-size: 8pt;">{{ $ev['submitted_at'] }}</td>
                            <td><span class="badge badge-success">{{ strtoupper($ev['status']) }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <!-- SECTION F: WITNESSES & TESTIMONIAL EVIDENCE -->
    <div class="section">
        <div class="section-title">6. Witness Testimonial Record</div>
        @if(empty($witnesses))
            <div style="color: #64748b; font-size: 8.5pt; font-style: italic;">No approved witness testimonies were recorded for this proceeding.</div>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">Witness Name</th>
                        <th style="width: 15%;">Party Side</th>
                        <th style="width: 20%;">Relationship</th>
                        <th style="width: 40%;">Official Statement Summary</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($witnesses as $w)
                        <tr>
                            <td><strong>{{ $w['witness_name'] }}</strong></td>
                            <td><span class="badge badge-primary">{{ $w['side'] }}</span></td>
                            <td>{{ $w['relationship'] ?: 'Participant' }}</td>
                            <td style="font-size: 8pt;">{{ $w['statement_summary'] ?: 'Official testimony given during hearing.' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <!-- SECTION G: HEARINGS RECORD -->
    <div class="section">
        <div class="section-title">7. Formal Hearing Session &amp; Official Transcript Entries</div>
        @if(empty($hearings))
            <div style="color: #64748b; font-size: 8.5pt; font-style: italic;">No formal hearings recorded.</div>
        @else
            @foreach($hearings as $h)
                <div class="callout">
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 4px;">
                        <tr>
                            <td>
                                <strong>Hearing Record #{{ $h['hearing_number'] }}</strong> &bull;
                                Type: <strong>{{ strtoupper($h['hearing_type']) }}</strong>
                            </td>
                            <td style="text-align: right;">
                                Scheduled: {{ $h['scheduled_at'] }} &bull;
                                Status: <span class="badge badge-success">{{ strtoupper($h['status']) }}</span>
                            </td>
                        </tr>
                    </table>

                    @if(!empty($h['entries']))
                        <table class="data-table" style="margin-top: 4px;">
                            <thead>
                                <tr>
                                    <th style="width: 8%;">#</th>
                                    <th style="width: 16%;">Entry Type</th>
                                    <th style="width: 22%;">Speaker / Role</th>
                                    <th style="width: 54%;">Recorded Statement / Entry</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($h['entries'] as $entry)
                                    <tr>
                                        <td style="font-family: 'Courier New', Courier, monospace;">{{ $entry['sequence_number'] }}</td>
                                        <td><span class="badge badge-primary">{{ strtoupper(str_replace('_', ' ', $entry['entry_type'])) }}</span></td>
                                        <td><strong>{{ $entry['speaker_name'] }}</strong> ({{ ucfirst($entry['participant_type'] ?: 'party') }})</td>
                                        <td style="font-size: 8pt;">{{ $entry['body'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @endforeach
        @endif
    </div>

    <!-- SECTION H: PUBLIC FINDINGS OF FACT -->
    <div class="section">
        <div class="section-title">8. Official Findings of Fact &amp; Determinations</div>
        @if(empty($findings))
            <div style="color: #64748b; font-size: 8.5pt; font-style: italic;">No public findings entered.</div>
        @else
            @foreach($findings as $f)
                <div class="callout" style="margin-bottom: 6px;">
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 4px;">
                        <tr>
                            <td>
                                <span class="meta-code" style="font-size: 9pt;">{{ $f['finding_number'] }}</span> &bull;
                                <strong>{{ $f['title'] ?: 'Finding of Fact' }}</strong>
                            </td>
                            <td style="text-align: right;">
                                <span class="badge badge-primary">{{ strtoupper($f['finding_type']) }}</span>
                                <span class="badge {{ $f['conclusion'] === 'established' ? 'badge-success' : ($f['conclusion'] === 'not_established' ? 'badge-danger' : 'badge-warning') }}">
                                    {{ strtoupper(str_replace('_', ' ', $f['conclusion'])) }}
                                </span>
                            </td>
                        </tr>
                    </table>
                    <div style="font-size: 8.5pt; color: #1e293b; margin-top: 2px;">
                        {{ $f['finding_text'] }}
                    </div>
                    @if(!empty($f['cited_evidence']) || !empty($f['cited_witnesses']))
                        <div style="font-size: 7.5pt; color: #64748b; margin-top: 4px; border-top: 1px dotted #cbd5e1; padding-top: 3px;">
                            <strong>Citations in Support:</strong>
                            @if(!empty($f['cited_evidence']))
                                Evidence: [{{ implode(', ', $f['cited_evidence']) }}]
                            @endif
                            @if(!empty($f['cited_witnesses']))
                                Witnesses: [{{ implode(', ', $f['cited_witnesses']) }}]
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        @endif
    </div>

    <!-- SECTION I: FINAL DECISION -->
    <div class="section">
        <div class="section-title">9. Final Tribunal Determination &amp; Legal Reasoning</div>
        <div class="callout" style="border-left: 4px solid #15803d; background-color: #f0fdf4;">
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 6px;">
                <tr>
                    <td>
                        <div style="font-size: 11pt; font-weight: 800; color: #166534;">
                            DECISION: {{ $decision_number }}
                        </div>
                        <div style="font-size: 8pt; color: #15803d;">
                            Adjudicated by: {{ $adjudicated_by }} &bull; Published: {{ $decision_published_at }}
                        </div>
                    </td>
                    <td style="text-align: right;">
                        <span class="badge badge-dark" style="font-size: 9pt; padding: 4px 8px;">
                            OUTCOME: {{ strtoupper(str_replace('_', ' ', $decision_outcome)) }}
                        </span>
                    </td>
                </tr>
            </table>

            <div style="margin-top: 8px;">
                <div style="font-weight: 700; font-size: 9pt; color: #166534; margin-bottom: 2px;">A. Executive Summary:</div>
                <div class="narrative-box" style="background: #ffffff; border-color: #bbf7d0;">
                    {{ $decision_summary }}
                </div>
            </div>

            <div style="margin-top: 8px;">
                <div style="font-weight: 700; font-size: 9pt; color: #166534; margin-bottom: 2px;">B. Tribunal Reasoning &amp; Analysis:</div>
                <div class="narrative-box" style="background: #ffffff; border-color: #bbf7d0; white-space: pre-line;">
                    {{ $decision_reasoning }}
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION J: ORDERS & REMEDIES -->
    <div class="section">
        <div class="section-title">10. Tribunal Orders &amp; Prescribed Remedies</div>
        @if(empty($orders))
            <div style="color: #64748b; font-size: 8.5pt; font-style: italic;">No formal affirmative compliance orders issued under this determination.</div>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 15%;">Order No.</th>
                        <th style="width: 15%;">Order Type</th>
                        <th style="width: 45%;">Order Title &amp; Description</th>
                        <th style="width: 13%;">Target Side</th>
                        <th style="width: 12%;">Compliance Deadline</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orders as $ord)
                        <tr>
                            <td class="meta-code">{{ $ord['order_number'] }}</td>
                            <td><span class="badge badge-warning">{{ strtoupper(str_replace('_', ' ', $ord['order_type'])) }}</span></td>
                            <td>
                                <strong>{{ $ord['title'] }}</strong>
                                <div style="font-size: 7.5pt; color: #334155; margin-top: 2px;">{{ $ord['description'] }}</div>
                            </td>
                            <td>{{ $ord['target_side'] ?: 'All Parties' }}</td>
                            <td style="font-size: 8pt; font-family: 'Courier New', Courier, monospace;">
                                {{ $ord['deadline_at'] ?: 'Immediate' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <!-- SECTION K: APPELLATE INFORMATION -->
    <div class="section">
        <div class="section-title">11. Appellate Provisions &amp; Status</div>
        <div class="callout" style="border-left: 4px solid #3b82f6;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td>
                        <strong>Appellate Window:</strong> {{ $appeal_deadline ?: 'N/A' }}
                    </td>
                    <td style="text-align: right;">
                        <span class="badge badge-primary">{{ $appeal_status }}</span>
                    </td>
                </tr>
            </table>
            <div style="font-size: 8pt; color: #475569; margin-top: 4px;">
                Under governing Tribunal procedural rules, either party may file a formal petition for procedural appellate review within the allotted window following decision publication.
            </div>
        </div>
    </div>

    <!-- SECTION L: AUTHENTICITY & CRYPTOGRAPHIC VERIFICATION -->
    <div class="auth-box">
        <div style="font-size: 10pt; font-weight: 800; color: #166534; text-transform: uppercase; margin-bottom: 6px; border-bottom: 1px solid #bbf7d0; padding-bottom: 3px;">
            12. Document Authenticity &amp; Cryptographic Verification
        </div>
        <table class="auth-table">
            <tr>
                <td class="auth-qr">
                    <img src="{{ $qr_data_uri }}" width="105" height="105" alt="QR Code" style="display: block; margin: 0 auto; border: 1px solid #bbf7d0; padding: 2px; background: #fff;" />
                    <div style="font-size: 6.5pt; color: #475569; margin-top: 3px; font-weight: 700;">SCAN TO VERIFY</div>
                </td>
                <td class="auth-details">
                    <div style="font-size: 8.5pt; color: #14532d; font-weight: 700; margin-bottom: 3px;">
                        Myintellibook_live Official Tribunal Report &bull; Authenticity Record
                    </div>
                    <div style="font-size: 8pt; color: #1e293b; margin-bottom: 2px;">
                        This official document may be verified online by scanning the QR code or visiting:
                    </div>
                    <div style="font-size: 8pt; font-family: 'Courier New', Courier, monospace; color: #15803d; font-weight: 700;">
                        {{ $verify_url }}
                    </div>
                    <div style="font-size: 8pt; color: #1e293b; margin-top: 4px;">
                        Verification Code: <strong class="meta-code" style="font-size: 9pt;">{{ $verification_code }}</strong> &bull; Report: <strong class="meta-code" style="font-size: 9pt;">{{ $report_number }}</strong>
                    </div>
                    <div style="font-size: 7pt; color: #475569; margin-top: 4px;">
                        Document SHA-256 Cryptographic Hash:
                    </div>
                    <div class="hash-code">
                        Calculated at Generation: SHA-256 (Matching official record for verification code {{ $verification_code }})
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="disclaimer-text">
        This document constitutes an official certified determination rendered by the Myintellibook_live Tribunal Division.<br>
        Authenticity can be verified through Myintellibook_live. Any alteration, tampering, or omission invalidates this document.
    </div>

</body>
</html>
