import 'dart:io';
import 'dart:typed_data';

import 'package:cross_file/cross_file.dart';
import 'package:gal/gal.dart';
import 'package:share_plus/share_plus.dart';

import 'receipt_delivery_result.dart';

Future<void> deliverPdfReceipt(Uint8List bytes, String fileName) async {
  await SharePlus.instance.share(
    ShareParams(
      files: <XFile>[
        XFile.fromData(bytes, name: fileName, mimeType: 'application/pdf'),
      ],
      title: 'Withdrawal receipt',
      subject: 'TLStore withdrawal receipt',
    ),
  );
}

Future<ImageReceiptDeliveryResult> deliverImageReceipt(
  Uint8List bytes,
  String fileName,
) async {
  if (Platform.isAndroid || Platform.isIOS) {
    try {
      final hasAccess = await Gal.hasAccess();
      final granted = hasAccess || await Gal.requestAccess();
      if (granted) {
        await Gal.putImageBytes(bytes, name: fileName);
        return ImageReceiptDeliveryResult.gallery;
      }
    } catch (_) {
      // If gallery access is denied or unavailable, offer the system share UI.
    }
  }

  await SharePlus.instance.share(
    ShareParams(
      files: <XFile>[
        XFile.fromData(bytes, name: fileName, mimeType: 'image/png'),
      ],
      title: 'Withdrawal receipt image',
      subject: 'TLStore withdrawal receipt',
    ),
  );
  return ImageReceiptDeliveryResult.shared;
}
