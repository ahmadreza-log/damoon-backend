/**
 * HTTP status codes and the Reply helper.
 * message is always taken from this list based on the numeric code.
 * Source: https://en.wikipedia.org/wiki/List_of_HTTP_status_codes
 */
import type { Response } from "express";

export const Codes: Record<number, string> = {
  100: "Continue",
  101: "Switching Protocols",
  102: "Processing",
  103: "Early Hints",
  200: "OK",
  201: "Created",
  202: "Accepted",
  203: "Non-Authoritative Information",
  204: "No Content",
  205: "Reset Content",
  206: "Partial Content",
  207: "Multi-Status",
  208: "Already Reported",
  218: "This is fine",
  226: "IM Used",
  300: "Multiple Choices",
  301: "Moved Permanently",
  302: "Found",
  303: "See Other",
  304: "Not Modified",
  305: "Use Proxy",
  306: "Switch Proxy",
  307: "Temporary Redirect",
  308: "Permanent Redirect",
  400: "Bad Request",
  401: "Unauthorized",
  402: "Payment Required",
  403: "Forbidden",
  404: "Not Found",
  405: "Method Not Allowed",
  406: "Not Acceptable",
  407: "Proxy Authentication Required",
  408: "Request Timeout",
  409: "Conflict",
  410: "Gone",
  411: "Length Required",
  412: "Precondition Failed",
  413: "Content Too Large",
  414: "URI Too Long",
  415: "Unsupported Media Type",
  416: "Range Not Satisfiable",
  417: "Expectation Failed",
  418: "I'm a teapot",
  419: "Page Expired",
  421: "Misdirected Request",
  422: "Unprocessable Content",
  423: "Locked",
  424: "Failed Dependency",
  425: "Too Early",
  426: "Upgrade Required",
  428: "Precondition Required",
  429: "Too Many Requests",
  431: "Request Header Fields Too Large",
  440: "Login Time-out",
  444: "No Response",
  449: "Retry With",
  450: "Blocked by Windows Parental Controls",
  451: "Unavailable For Legal Reasons",
  460: "Client Closed Connection",
  463: "Too Many X-Forwarded-For Addresses",
  464: "Incompatible Protocol",
  494: "Request Header Too Large",
  495: "SSL Certificate Error",
  496: "SSL Certificate Required",
  497: "HTTP Request Sent to HTTPS Port",
  498: "Invalid Token",
  499: "Client Closed Request",
  500: "Internal Server Error",
  501: "Not Implemented",
  502: "Bad Gateway",
  503: "Service Unavailable",
  504: "Gateway Timeout",
  505: "HTTP Version Not Supported",
  506: "Variant Also Negotiates",
  507: "Insufficient Storage",
  508: "Loop Detected",
  509: "Bandwidth Limit Exceeded",
  510: "Not Extended",
  511: "Network Authentication Required",
  520: "Web Server Returned an Unknown Error",
  521: "Web Server Is Down",
  522: "Connection Timed Out",
  523: "Origin Is Unreachable",
  524: "A Timeout Occurred",
  525: "SSL Handshake Failed",
  526: "Invalid SSL Certificate",
  527: "Railgun Error",
  529: "Site is overloaded",
  530: "Origin DNS Error",
  540: "Temporarily Disabled",
  561: "Unauthorized",
  598: "Network Read Timeout Error",
  599: "Network Connect Timeout Error",
  783: "Unexpected Token",
  999: "Request Denied",
};

/**
 * Look up the standard message for an HTTP status code.
 */
export function Phrase(code: number): string {
  return Codes[code] ?? "Unknown Status";
}

/**
 * Send JSON with HTTP code and the matching message from Codes.
 * Extra body fields are allowed; message and code always come from the list.
 */
export function Reply(
  res: Response,
  code: number,
  body: Record<string, unknown> = {}
): void {
  res.status(code).json({
    ...body,
    code,
    message: Phrase(code),
  });
}
