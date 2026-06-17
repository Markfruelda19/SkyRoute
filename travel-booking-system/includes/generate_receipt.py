#!/usr/bin/env python3
"""
SkyRoute — Booking Receipt Generator
Called by receipt.php with booking data as JSON via stdin.
"""
import sys
import json
import os
from io import BytesIO
from datetime import datetime

from reportlab.lib.pagesizes import A4
from reportlab.lib import colors
from reportlab.lib.units import mm
from reportlab.lib.styles import ParagraphStyle
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle,
    HRFlowable, KeepTogether
)
from reportlab.lib.enums import TA_LEFT, TA_CENTER, TA_RIGHT

# ── Palette ──────────────────────────────────────────────────────────────────
NAVY      = colors.HexColor('#0a1628')
NAVY_MID  = colors.HexColor('#112240')
NAVY_LITE = colors.HexColor('#1a3a5c')
GOLD      = colors.HexColor('#c9a84c')
GOLD_LITE = colors.HexColor('#e8c97a')
WHITE     = colors.HexColor('#f8f9fc')
GRAY      = colors.HexColor('#8892a4')
GRAY_LITE = colors.HexColor('#dde3ed')
GREEN     = colors.HexColor('#4caf82')
TEXT      = colors.HexColor('#1a2540')

W, H = A4   # 210 × 297 mm
MARGIN = 18 * mm

# ── Styles ────────────────────────────────────────────────────────────────────
def make_styles():
    return {
        'brand': ParagraphStyle('brand',
            fontName='Helvetica-Bold', fontSize=22,
            textColor=GOLD, spaceAfter=2),
        'brand_tag': ParagraphStyle('brand_tag',
            fontName='Helvetica', fontSize=8,
            textColor=GRAY, spaceAfter=0),
        'h1': ParagraphStyle('h1',
            fontName='Helvetica-Bold', fontSize=16,
            textColor=NAVY, spaceAfter=4),
        'h2': ParagraphStyle('h2',
            fontName='Helvetica-Bold', fontSize=11,
            textColor=GOLD, spaceBefore=6, spaceAfter=4),
        'label': ParagraphStyle('label',
            fontName='Helvetica-Bold', fontSize=8,
            textColor=GRAY, spaceAfter=1),
        'value': ParagraphStyle('value',
            fontName='Helvetica', fontSize=10,
            textColor=TEXT, spaceAfter=0),
        'value_bold': ParagraphStyle('value_bold',
            fontName='Helvetica-Bold', fontSize=10,
            textColor=TEXT),
        'txn': ParagraphStyle('txn',
            fontName='Courier-Bold', fontSize=9,
            textColor=NAVY_LITE),
        'small': ParagraphStyle('small',
            fontName='Helvetica', fontSize=8,
            textColor=GRAY, spaceAfter=0),
        'total_label': ParagraphStyle('total_label',
            fontName='Helvetica-Bold', fontSize=12,
            textColor=WHITE),
        'total_value': ParagraphStyle('total_value',
            fontName='Helvetica-Bold', fontSize=14,
            textColor=GOLD),
        'footer': ParagraphStyle('footer',
            fontName='Helvetica', fontSize=7.5,
            textColor=GRAY, alignment=TA_CENTER),
        'confirmed': ParagraphStyle('confirmed',
            fontName='Helvetica-Bold', fontSize=9,
            textColor=GREEN),
    }

# ── Helpers ───────────────────────────────────────────────────────────────────
def fmt_price(val):
    try:
        return f"\u20b1{float(val):,.2f}"
    except Exception:
        return str(val)

def fmt_date(s, fmt='%b %d, %Y'):
    if not s:
        return '—'
    for f in ('%Y-%m-%d %H:%M:%S', '%Y-%m-%d', '%Y-%m-%dT%H:%M:%S'):
        try:
            return datetime.strptime(s, f).strftime(fmt)
        except Exception:
            pass
    return str(s)

def fmt_datetime(s):
    return fmt_date(s, '%b %d, %Y  %I:%M %p')

def info_pair(label, value, styles, label_width=60*mm, value_width=None):
    """Returns a 2-col mini-table for label/value pairs."""
    col_widths = [label_width, value_width] if value_width else [label_width, None]
    t = Table([[Paragraph(label, styles['label']),
                Paragraph(str(value), styles['value'])]], colWidths=col_widths)
    t.setStyle(TableStyle([
        ('VALIGN', (0,0), (-1,-1), 'TOP'),
        ('LEFTPADDING', (0,0), (-1,-1), 0),
        ('RIGHTPADDING', (0,0), (-1,-1), 0),
        ('TOPPADDING', (0,0), (-1,-1), 0),
        ('BOTTOMPADDING', (0,0), (-1,-1), 3),
    ]))
    return t

# ── Header band ───────────────────────────────────────────────────────────────
def build_header(styles, data, usable_w):
    # Left: logo + brand
    left = [
        Paragraph("&#9992; SkyRoute", styles['brand']),
        Paragraph("Travel Booking Receipt", styles['brand_tag']),
    ]
    # Right: receipt meta
    right = [
        Paragraph(f"Receipt #{data['booking_id']}", styles['value_bold']),
        Spacer(1, 2),
        Paragraph(f"Issued: {fmt_date(data['created_at'])}", styles['small']),
        Spacer(1, 2),
        Paragraph(f"TXN: {data['transaction_id']}", styles['txn']),
    ]

    right_para_style = ParagraphStyle('right', fontName='Helvetica',
        fontSize=9, textColor=TEXT, alignment=TA_RIGHT)
    right_bold = ParagraphStyle('right_bold', fontName='Helvetica-Bold',
        fontSize=10, textColor=TEXT, alignment=TA_RIGHT)
    right_small = ParagraphStyle('right_small', fontName='Helvetica',
        fontSize=8, textColor=GRAY, alignment=TA_RIGHT)
    right_txn = ParagraphStyle('right_txn', fontName='Courier-Bold',
        fontSize=8, textColor=NAVY_LITE, alignment=TA_RIGHT)

    right_col = [
        Paragraph(f"Receipt #{data['booking_id']}", right_bold),
        Spacer(1, 2),
        Paragraph(f"Issued: {fmt_date(data['created_at'])}", right_small),
        Spacer(1, 2),
        Paragraph(data['transaction_id'], right_txn),
    ]

    tbl = Table(
        [[left, right_col]],
        colWidths=[usable_w * 0.55, usable_w * 0.45]
    )
    tbl.setStyle(TableStyle([
        ('VALIGN', (0,0), (-1,-1), 'TOP'),
        ('LEFTPADDING', (0,0), (-1,-1), 0),
        ('RIGHTPADDING', (0,0), (-1,-1), 0),
        ('TOPPADDING', (0,0), (-1,-1), 0),
        ('BOTTOMPADDING', (0,0), (-1,-1), 0),
        ('ALIGN', (1,0), (1,0), 'RIGHT'),
    ]))
    return tbl

# ── Status pill ───────────────────────────────────────────────────────────────
def status_pill(status, styles, usable_w):
    color_map = {
        'confirmed': (GREEN, colors.HexColor('#e8f8f1')),
        'pending':   (GOLD,  colors.HexColor('#fdf6e3')),
        'cancelled': (colors.HexColor('#e05c5c'), colors.HexColor('#fdeaea')),
    }
    fg, bg = color_map.get(status, (GRAY, WHITE))
    label_style = ParagraphStyle('pill', fontName='Helvetica-Bold',
        fontSize=9, textColor=fg, alignment=TA_CENTER)
    pill = Table([[Paragraph(f"  {status.upper()}  ", label_style)]],
                 colWidths=[40*mm])
    pill.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,-1), bg),
        ('ROUNDEDCORNERS', [4]),
        ('TOPPADDING', (0,0), (-1,-1), 4),
        ('BOTTOMPADDING', (0,0), (-1,-1), 4),
        ('LEFTPADDING', (0,0), (-1,-1), 8),
        ('RIGHTPADDING', (0,0), (-1,-1), 8),
    ]))
    wrapper = Table([[Paragraph("Booking Status", styles['label']), pill]],
                    colWidths=[usable_w - 50*mm, 50*mm])
    wrapper.setStyle(TableStyle([
        ('VALIGN', (0,0), (-1,-1), 'MIDDLE'),
        ('LEFTPADDING', (0,0), (-1,-1), 0),
        ('RIGHTPADDING', (0,0), (-1,-1), 0),
        ('TOPPADDING', (0,0), (-1,-1), 0),
        ('BOTTOMPADDING', (0,0), (-1,-1), 0),
        ('ALIGN', (1,0), (1,0), 'RIGHT'),
    ]))
    return wrapper

# ── Section box ───────────────────────────────────────────────────────────────
def section_box(title, rows, styles, usable_w):
    """Renders a titled section with a subtle background."""
    items = [Paragraph(title, styles['h2'])]
    col_w = (usable_w - 8*mm) / 2

    # Group rows into pairs for 2-col layout
    for i in range(0, len(rows), 2):
        left_cell = [Paragraph(rows[i][0], styles['label']),
                     Paragraph(str(rows[i][1]), styles['value'])]
        if i + 1 < len(rows):
            right_cell = [Paragraph(rows[i+1][0], styles['label']),
                          Paragraph(str(rows[i+1][1]), styles['value'])]
        else:
            right_cell = ['']

        t = Table([[left_cell, right_cell]], colWidths=[col_w, col_w])
        t.setStyle(TableStyle([
            ('VALIGN', (0,0), (-1,-1), 'TOP'),
            ('LEFTPADDING', (0,0), (-1,-1), 0),
            ('RIGHTPADDING', (0,0), (-1,-1), 0),
            ('TOPPADDING', (0,0), (-1,-1), 0),
            ('BOTTOMPADDING', (0,0), (-1,-1), 5),
        ]))
        items.append(t)

    inner = Table([[items]], colWidths=[usable_w - 8*mm])
    inner.setStyle(TableStyle([
        ('LEFTPADDING', (0,0), (-1,-1), 4*mm),
        ('RIGHTPADDING', (0,0), (-1,-1), 4*mm),
        ('TOPPADDING', (0,0), (-1,-1), 4*mm),
        ('BOTTOMPADDING', (0,0), (-1,-1), 4*mm),
        ('BACKGROUND', (0,0), (-1,-1), colors.HexColor('#f0f4f8')),
        ('ROUNDEDCORNERS', [6]),
    ]))
    return inner

# ── Price breakdown ───────────────────────────────────────────────────────────
def price_table(data, styles, usable_w):
    btype = data.get('booking_type', 'flight')
    price = float(data.get('total_price', 0))
    guests = int(data.get('guests', 1))

    if btype == 'flight':
        unit_price = price / guests if guests else price
        rows = [
            ["Base fare", f"{fmt_price(unit_price)} × {guests} passenger{'s' if guests > 1 else ''}"],
            ["Airport taxes & fees", "Included"],
            ["Seat selection", "Included"],
        ]
    else:
        checkin  = data.get('check_in')
        checkout = data.get('check_out')
        nights = 1
        if checkin and checkout:
            try:
                d1 = datetime.strptime(checkin, '%Y-%m-%d')
                d2 = datetime.strptime(checkout, '%Y-%m-%d')
                nights = max(1, (d2 - d1).days)
            except Exception:
                pass
        unit_price = price / nights if nights else price
        rows = [
            ["Nightly rate", f"{fmt_price(unit_price)} × {nights} night{'s' if nights > 1 else ''}"],
            ["Guests", str(guests)],
            ["Service charge", "Included"],
        ]

    # Build table
    label_style = ParagraphStyle('pl', fontName='Helvetica', fontSize=9, textColor=TEXT)
    value_style = ParagraphStyle('pv', fontName='Helvetica', fontSize=9,
                                  textColor=TEXT, alignment=TA_RIGHT)

    body = [[Paragraph(r[0], label_style), Paragraph(r[1], value_style)] for r in rows]

    t = Table(body, colWidths=[usable_w * 0.65, usable_w * 0.35])
    t.setStyle(TableStyle([
        ('LEFTPADDING', (0,0), (-1,-1), 4*mm),
        ('RIGHTPADDING', (0,0), (-1,-1), 4*mm),
        ('TOPPADDING', (0,0), (-1,-1), 3),
        ('BOTTOMPADDING', (0,0), (-1,-1), 3),
        ('LINEBELOW', (0,-1), (-1,-1), 0.5, GRAY_LITE),
        ('BACKGROUND', (0,0), (-1,-1), colors.HexColor('#f8fafb')),
        ('ROWBACKGROUNDS', (0,0), (-1,-1), [colors.white, colors.HexColor('#f8fafb')]),
    ]))

    # Total row
    total_label = ParagraphStyle('tl', fontName='Helvetica-Bold', fontSize=11,
                                  textColor=WHITE)
    total_val   = ParagraphStyle('tv', fontName='Helvetica-Bold', fontSize=13,
                                  textColor=GOLD, alignment=TA_RIGHT)
    total_row = Table(
        [[Paragraph("TOTAL AMOUNT PAID", total_label),
          Paragraph(fmt_price(price), total_val)]],
        colWidths=[usable_w * 0.65, usable_w * 0.35]
    )
    total_row.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,-1), NAVY),
        ('LEFTPADDING', (0,0), (-1,-1), 4*mm),
        ('RIGHTPADDING', (0,0), (-1,-1), 4*mm),
        ('TOPPADDING', (0,0), (-1,-1), 5),
        ('BOTTOMPADDING', (0,0), (-1,-1), 5),
        ('ROUNDEDCORNERS', [0, 0, 4, 4]),
    ]))

    return [Paragraph("Price Breakdown", styles['h2']), t, total_row]

# ── Main builder ──────────────────────────────────────────────────────────────
def build_receipt(data, output_path):
    styles = make_styles()
    usable_w = W - 2 * MARGIN

    buf = BytesIO()
    doc = SimpleDocTemplate(
        output_path,
        pagesize=A4,
        leftMargin=MARGIN, rightMargin=MARGIN,
        topMargin=MARGIN, bottomMargin=MARGIN,
        title=f"SkyRoute Booking Receipt #{data['booking_id']}",
        author="SkyRoute Travel",
    )

    story = []

    # ── Header ──
    story.append(build_header(styles, data, usable_w))
    story.append(Spacer(1, 4*mm))
    story.append(HRFlowable(width=usable_w, thickness=2, color=GOLD, spaceAfter=4*mm))

    # ── Title + status ──
    story.append(Paragraph("Booking Confirmation", styles['h1']))
    story.append(Spacer(1, 2*mm))
    story.append(status_pill(data.get('status', 'confirmed'), styles, usable_w))
    story.append(Spacer(1, 5*mm))

    # ── Passenger / Guest info ──
    passenger_rows = [
        ["Full Name", data.get('user_name', '—')],
        ["Email Address", data.get('user_email', '—')],
        ["Booking Date", fmt_date(data.get('created_at'))],
        ["Number of " + ("Passengers" if data['booking_type'] == 'flight' else "Guests"),
         str(data.get('guests', 1))],
    ]
    story.append(section_box("Passenger Information", passenger_rows, styles, usable_w))
    story.append(Spacer(1, 4*mm))

    # ── Flight or Hotel details ──
    btype = data.get('booking_type', 'flight')
    if btype == 'flight':
        detail_rows = [
            ["Airline",      data.get('airline', '—')],
            ["Flight Type",  "Economy"],
            ["Origin",       data.get('origin', '—')],
            ["Destination",  data.get('destination', '—')],
            ["Departure",    fmt_datetime(data.get('departure_time'))],
            ["Arrival",      fmt_datetime(data.get('arrival_time'))],
        ]
        story.append(section_box("Flight Details", detail_rows, styles, usable_w))
    else:
        detail_rows = [
            ["Hotel",        data.get('hotel_name', '—')],
            ["Location",     data.get('location', '—')],
            ["Check-in",     fmt_date(data.get('check_in'))],
            ["Check-out",    fmt_date(data.get('check_out'))],
            ["Rating",       f"{data.get('rating', '—')} / 5.0"],
            ["Room Type",    "Standard Room"],
        ]
        story.append(section_box("Hotel Details", detail_rows, styles, usable_w))

    story.append(Spacer(1, 4*mm))

    # ── Price breakdown ──
    story.extend(price_table(data, styles, usable_w))
    story.append(Spacer(1, 6*mm))

    # ── Important notes ──
    notes_style = ParagraphStyle('note', fontName='Helvetica', fontSize=8,
                                  textColor=GRAY, spaceAfter=3)
    notes_title = ParagraphStyle('nt', fontName='Helvetica-Bold', fontSize=9,
                                  textColor=TEXT, spaceAfter=4)
    notes = [
        Paragraph("Important Information", notes_title),
        Paragraph("• This receipt serves as your official booking confirmation.", notes_style),
        Paragraph("• Please present this document at check-in.", notes_style),
        Paragraph("• Cancellations must be made 24 hours prior to the booking date.", notes_style),
        Paragraph("• This is a simulated booking — no real payment was charged.", notes_style),
    ]
    notes_box = Table([[notes]], colWidths=[usable_w])
    notes_box.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,-1), colors.HexColor('#fffbf0')),
        ('LEFTPADDING', (0,0), (-1,-1), 4*mm),
        ('RIGHTPADDING', (0,0), (-1,-1), 4*mm),
        ('TOPPADDING', (0,0), (-1,-1), 3*mm),
        ('BOTTOMPADDING', (0,0), (-1,-1), 3*mm),
        ('LINERIGHT', (0,0), (0,-1), 3, GOLD),
        ('ROUNDEDCORNERS', [4]),
    ]))
    story.append(notes_box)
    story.append(Spacer(1, 8*mm))

    # ── Footer ──
    story.append(HRFlowable(width=usable_w, thickness=0.5, color=GRAY_LITE, spaceAfter=3*mm))
    story.append(Paragraph(
        f"SkyRoute Travel  •  Receipt #{data['booking_id']}  •  "
        f"Generated on {datetime.now().strftime('%B %d, %Y at %I:%M %p')}",
        styles['footer']
    ))
    story.append(Paragraph(
        "This is an automatically generated document. For support, contact support@skyroute.com",
        styles['footer']
    ))

    doc.build(story)


# ── Entry point ───────────────────────────────────────────────────────────────
if __name__ == '__main__':
    raw = sys.stdin.read()
    data = json.loads(raw)
    output_path = data.get('output_path', '/tmp/receipt.pdf')
    build_receipt(data, output_path)
    print(output_path)
