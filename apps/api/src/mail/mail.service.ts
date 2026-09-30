import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import * as nodemailer from 'nodemailer';

export interface Mensagem {
  para: string;
  assunto: string;
  texto: string;
  html?: string;
}

// Envia e-mails por SMTP. Localmente o servidor é o Mailpit (simulação);
// em produção basta apontar as variáveis SMTP_* para o servidor real.
@Injectable()
export class MailService {
  private readonly logger = new Logger(MailService.name);
  private readonly transporte: nodemailer.Transporter;

  constructor(private readonly config: ConfigService) {
    const user = config.get<string>('SMTP_USER');
    this.transporte = nodemailer.createTransport({
      host: config.get<string>('SMTP_HOST', 'localhost'),
      port: Number(config.get('SMTP_PORT', 1025)),
      secure: config.get('SMTP_SECURE') === 'true',
      auth: user ? { user, pass: config.get<string>('SMTP_PASS') } : undefined,
    });
  }

  async enviar(msg: Mensagem): Promise<void> {
    await this.transporte.sendMail({
      from: this.config.get<string>('MAIL_FROM', 'Laboratório <nao-responda@laboratorio.local>'),
      to: msg.para,
      subject: msg.assunto,
      text: msg.texto,
      html: msg.html,
    });
    this.logger.log(`E-mail enviado para ${msg.para}: ${msg.assunto}`);
  }
}
