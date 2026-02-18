<?php

namespace Pace\Enum;

enum ReportExportType: int
{
    const PDF = 2;
    const RTF = 3;
    const HTML = 4;
    const CSV = 5;
    const XLS = 6;
    const XLSX = 7;
    const XML = 8;
    const TXT = 9;
}
