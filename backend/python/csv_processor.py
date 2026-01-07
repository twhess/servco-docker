#!/usr/bin/env python3
"""
CSV Processor Module

High-performance CSV processing using pandas.
Designed to be easily migrated to FastAPI in the future.

Usage (CLI):
    python csv_processor.py --action filter --input data.csv --query "column:value"
    python csv_processor.py --action aggregate --input data.csv --group-by "category" --agg "sum:amount"
    python csv_processor.py --action search --input data.csv --term "searchterm" --columns "name,address"

Usage (as module):
    from csv_processor import CsvProcessor
    processor = CsvProcessor()
    result = processor.filter_data(csv_content, {"address": "Dayton"})
"""

import argparse
import json
import sys
import io
from typing import Any, Optional

import pandas as pd


class CsvProcessor:
    """
    CSV processing class with methods that can easily become FastAPI endpoints.

    Each method returns a dict that can be JSON serialized.
    This makes migration to FastAPI straightforward:

    @app.post("/filter")
    async def filter_endpoint(request: FilterRequest):
        processor = CsvProcessor()
        return processor.filter_data(request.csv_content, request.filters)
    """

    def __init__(self):
        self.max_rows_output = 10000  # Safety limit for output

    def parse_csv(self, csv_content: str) -> pd.DataFrame:
        """Parse CSV string into DataFrame."""
        return pd.read_csv(io.StringIO(csv_content))

    def filter_data(
        self,
        csv_content: str,
        filters: dict[str, Any],
        case_sensitive: bool = False
    ) -> dict:
        """
        Filter CSV data based on column:value pairs.

        Args:
            csv_content: Raw CSV string
            filters: Dict of {column_name: search_value}
            case_sensitive: Whether to use case-sensitive matching

        Returns:
            Dict with filtered data and metadata

        Future FastAPI endpoint: POST /api/csv/filter
        """
        try:
            df = self.parse_csv(csv_content)
            original_count = len(df)

            for column, value in filters.items():
                if column not in df.columns:
                    # Try case-insensitive column match
                    column_lower = column.lower()
                    matching_cols = [c for c in df.columns if c.lower() == column_lower]
                    if matching_cols:
                        column = matching_cols[0]
                    else:
                        continue

                # Convert to string for contains matching
                col_values = df[column].astype(str)
                search_value = str(value)

                if case_sensitive:
                    mask = col_values.str.contains(search_value, na=False, regex=False)
                else:
                    mask = col_values.str.contains(
                        search_value, case=False, na=False, regex=False
                    )
                df = df[mask]

            # Limit output rows
            if len(df) > self.max_rows_output:
                df = df.head(self.max_rows_output)
                truncated = True
            else:
                truncated = False

            return {
                "success": True,
                "data": df.to_dict(orient="records"),
                "columns": list(df.columns),
                "row_count": len(df),
                "original_count": original_count,
                "truncated": truncated,
                "csv": df.to_csv(index=False)
            }

        except Exception as e:
            return {
                "success": False,
                "error": str(e),
                "data": [],
                "row_count": 0
            }

    def search_all_columns(
        self,
        csv_content: str,
        search_term: str,
        columns: Optional[list[str]] = None
    ) -> dict:
        """
        Search for a term across all (or specified) columns.

        Args:
            csv_content: Raw CSV string
            search_term: Term to search for
            columns: Optional list of columns to search (None = all)

        Returns:
            Dict with matching rows and metadata

        Future FastAPI endpoint: POST /api/csv/search
        """
        try:
            df = self.parse_csv(csv_content)
            original_count = len(df)

            if columns:
                # Filter to specified columns that exist
                search_cols = [c for c in columns if c in df.columns]
            else:
                search_cols = df.columns.tolist()

            # Create mask for any column containing the search term
            mask = pd.Series([False] * len(df))
            for col in search_cols:
                col_mask = df[col].astype(str).str.contains(
                    search_term, case=False, na=False, regex=False
                )
                mask = mask | col_mask

            df = df[mask]

            # Limit output rows
            if len(df) > self.max_rows_output:
                df = df.head(self.max_rows_output)
                truncated = True
            else:
                truncated = False

            return {
                "success": True,
                "data": df.to_dict(orient="records"),
                "columns": list(df.columns),
                "row_count": len(df),
                "original_count": original_count,
                "truncated": truncated,
                "csv": df.to_csv(index=False)
            }

        except Exception as e:
            return {
                "success": False,
                "error": str(e),
                "data": [],
                "row_count": 0
            }

    def aggregate_data(
        self,
        csv_content: str,
        group_by: list[str],
        aggregations: dict[str, str]
    ) -> dict:
        """
        Aggregate data with grouping.

        Args:
            csv_content: Raw CSV string
            group_by: Columns to group by
            aggregations: Dict of {column: agg_function}
                          e.g., {"amount": "sum", "count": "count"}

        Returns:
            Dict with aggregated data

        Future FastAPI endpoint: POST /api/csv/aggregate
        """
        try:
            df = self.parse_csv(csv_content)

            # Validate columns exist
            valid_group_by = [c for c in group_by if c in df.columns]
            if not valid_group_by:
                return {
                    "success": False,
                    "error": f"No valid group_by columns found. Available: {list(df.columns)}",
                    "data": [],
                    "row_count": 0
                }

            valid_aggs = {
                col: agg for col, agg in aggregations.items()
                if col in df.columns
            }

            if not valid_aggs:
                # Default to count if no valid aggregations
                valid_aggs = {df.columns[0]: "count"}

            result = df.groupby(valid_group_by).agg(valid_aggs).reset_index()

            return {
                "success": True,
                "data": result.to_dict(orient="records"),
                "columns": list(result.columns),
                "row_count": len(result),
                "csv": result.to_csv(index=False)
            }

        except Exception as e:
            return {
                "success": False,
                "error": str(e),
                "data": [],
                "row_count": 0
            }

    def get_summary(self, csv_content: str) -> dict:
        """
        Get summary statistics about the CSV.

        Args:
            csv_content: Raw CSV string

        Returns:
            Dict with summary info

        Future FastAPI endpoint: GET /api/csv/summary
        """
        try:
            df = self.parse_csv(csv_content)

            column_info = []
            for col in df.columns:
                col_data = {
                    "name": col,
                    "dtype": str(df[col].dtype),
                    "non_null_count": int(df[col].notna().sum()),
                    "null_count": int(df[col].isna().sum()),
                    "unique_count": int(df[col].nunique())
                }

                # Add numeric stats if applicable
                if pd.api.types.is_numeric_dtype(df[col]):
                    col_data["min"] = float(df[col].min()) if not pd.isna(df[col].min()) else None
                    col_data["max"] = float(df[col].max()) if not pd.isna(df[col].max()) else None
                    col_data["mean"] = float(df[col].mean()) if not pd.isna(df[col].mean()) else None
                    col_data["sum"] = float(df[col].sum()) if not pd.isna(df[col].sum()) else None

                column_info.append(col_data)

            return {
                "success": True,
                "row_count": len(df),
                "column_count": len(df.columns),
                "columns": column_info,
                "sample_data": df.head(5).to_dict(orient="records")
            }

        except Exception as e:
            return {
                "success": False,
                "error": str(e),
                "row_count": 0,
                "column_count": 0
            }

    def prepare_for_gemini(
        self,
        csv_content: str,
        filters: Optional[dict[str, Any]] = None,
        max_rows: int = 1000
    ) -> dict:
        """
        Prepare CSV data for Gemini analysis.
        Optionally filter first, then format for Gemini.

        Args:
            csv_content: Raw CSV string
            filters: Optional filters to apply first
            max_rows: Maximum rows to include

        Returns:
            Dict with formatted data for Gemini

        Future FastAPI endpoint: POST /api/csv/prepare-for-ai
        """
        try:
            df = self.parse_csv(csv_content)
            original_count = len(df)

            # Apply filters if provided
            if filters:
                for column, value in filters.items():
                    if column not in df.columns:
                        column_lower = column.lower()
                        matching_cols = [c for c in df.columns if c.lower() == column_lower]
                        if matching_cols:
                            column = matching_cols[0]
                        else:
                            continue

                    col_values = df[column].astype(str)
                    search_value = str(value)
                    mask = col_values.str.contains(
                        search_value, case=False, na=False, regex=False
                    )
                    df = df[mask]

            filtered_count = len(df)

            # Limit rows
            if len(df) > max_rows:
                df = df.head(max_rows)
                truncated = True
            else:
                truncated = False

            # Format as clean CSV for Gemini
            csv_for_gemini = df.to_csv(index=False)

            return {
                "success": True,
                "csv": csv_for_gemini,
                "columns": list(df.columns),
                "row_count": len(df),
                "original_count": original_count,
                "filtered_count": filtered_count,
                "truncated": truncated,
                "data": df.to_dict(orient="records")
            }

        except Exception as e:
            return {
                "success": False,
                "error": str(e),
                "csv": "",
                "row_count": 0
            }


def main():
    """CLI interface for the CSV processor."""
    parser = argparse.ArgumentParser(
        description="High-performance CSV processor using pandas"
    )
    parser.add_argument(
        "--action",
        required=True,
        choices=["filter", "search", "aggregate", "summary", "prepare"],
        help="Action to perform"
    )
    parser.add_argument(
        "--input",
        help="Path to input CSV file (or use --stdin for stdin)"
    )
    parser.add_argument(
        "--stdin",
        action="store_true",
        help="Read CSV from stdin"
    )
    parser.add_argument(
        "--filters",
        help='JSON object of column:value filters, e.g., \'{"address": "Dayton"}\''
    )
    parser.add_argument(
        "--term",
        help="Search term (for search action)"
    )
    parser.add_argument(
        "--columns",
        help="Comma-separated list of columns (for search/aggregate)"
    )
    parser.add_argument(
        "--group-by",
        help="Comma-separated columns to group by (for aggregate)"
    )
    parser.add_argument(
        "--agg",
        help='JSON object of aggregations, e.g., \'{"amount": "sum"}\''
    )
    parser.add_argument(
        "--max-rows",
        type=int,
        default=1000,
        help="Maximum rows to return (default: 1000)"
    )
    parser.add_argument(
        "--output-format",
        choices=["json", "csv"],
        default="json",
        help="Output format (default: json)"
    )

    args = parser.parse_args()

    # Read CSV content
    if args.stdin:
        csv_content = sys.stdin.read()
    elif args.input:
        with open(args.input, "r", encoding="utf-8") as f:
            csv_content = f.read()
    else:
        print(json.dumps({"success": False, "error": "No input provided"}))
        sys.exit(1)

    processor = CsvProcessor()
    processor.max_rows_output = args.max_rows

    result = {}

    if args.action == "filter":
        filters = json.loads(args.filters) if args.filters else {}
        result = processor.filter_data(csv_content, filters)

    elif args.action == "search":
        if not args.term:
            result = {"success": False, "error": "Search term required"}
        else:
            columns = args.columns.split(",") if args.columns else None
            result = processor.search_all_columns(csv_content, args.term, columns)

    elif args.action == "aggregate":
        group_by = args.group_by.split(",") if args.group_by else []
        aggs = json.loads(args.agg) if args.agg else {}
        result = processor.aggregate_data(csv_content, group_by, aggs)

    elif args.action == "summary":
        result = processor.get_summary(csv_content)

    elif args.action == "prepare":
        filters = json.loads(args.filters) if args.filters else None
        result = processor.prepare_for_gemini(csv_content, filters, args.max_rows)

    # Output result
    if args.output_format == "csv" and result.get("success") and "csv" in result:
        print(result["csv"])
    else:
        print(json.dumps(result, indent=2, default=str))


if __name__ == "__main__":
    main()
