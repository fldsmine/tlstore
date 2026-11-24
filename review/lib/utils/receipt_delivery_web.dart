import 'dart:html' as html;
import 'dart:typed_data';

import 'receipt_delivery_result.dart';

Future<void> deliverPdfReceipt(Uint8List bytes, String fileName) async {
  final blob = html.Blob(<Object>[bytes], 'application/pdf');
  final url = html.Url.createObjectUrlFromBlob(blob);
  final anchor = html.AnchorElement(href: url)
    ..download = fileName
    ..style.display = 'none';

  html.document.body?.children.add(anchor);
  anchor.click();
  anchor.remove();
  await Future<void>.delayed(const Duration(seconds: 1));
  html.Url.revokeObjectUrl(url);
}

Future<ImageReceiptDeliveryResult> deliverImageReceipt(
  Uint8List bytes,
  String fileName,
) async {
  final blob = html.Blob(<Object>[bytes], 'image/png');
  final url = html.Url.createObjectUrlFromBlob(blob);
  final anchor = html.AnchorElement(href: url)
    ..download = fileName
    ..style.display = 'none';

  html.document.body?.children.add(anchor);
  anchor.click();
  anchor.remove();
  await Future<void>.delayed(const Duration(seconds: 1));
  html.Url.revokeObjectUrl(url);
  return ImageReceiptDeliveryResult.downloaded;
}
