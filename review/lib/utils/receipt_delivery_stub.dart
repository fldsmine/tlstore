import 'dart:typed_data';

import 'package:cross_file/cross_file.dart';
import 'package:share_plus/share_plus.dart';

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
